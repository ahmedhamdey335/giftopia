<?php

namespace Tests\Feature;

use App\Enums\ColorSwatchRole;
use App\Enums\InsertType;
use App\Enums\OrderStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use App\Enums\ProofStatus;
use App\Mail\BalanceRequestedMail;
use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use App\Services\Studio\StudioConfiguration;
use App\Services\Studio\StudioQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_workflow_enforces_gates_and_runs_through_balance_and_delivery(): void
    {
        Mail::fake();
        Storage::fake('private_assets');
        config(['services.payments.fake_webhook_secret' => 'production-test-secret']);
        $customer = User::factory()->create();
        $producer = User::factory()->create(['role' => 'producer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $configuration = $this->completeConfiguration();
        $quote = app(StudioQuoteService::class)->create($configuration, $customer);
        $checkout = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'production-e2e')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($checkout->json('data.id'));
        $item = $order->items()->firstOrFail();
        $this->settle($order->payments()->firstOrFail());
        $item->refresh();

        $this->assertSame(ProductionStatus::AwaitingProof, $item->production_status);
        $this->assertSame(ProofStatus::Pending, $item->proof_status);
        $this->actingAs($customer, 'sanctum')->getJson('/api/admin/production-sheets')->assertForbidden();
        $cardResponse = $this->actingAs($producer, 'sanctum')
            ->getJson('/api/admin/production-sheets/'.$item->id)
            ->assertOk();
        $cardResponse->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.text', null)
            ->assertJsonPath('data.allowed_next_transitions', []);

        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::ReadyToStart->value,
        ])->assertUnprocessable();
        $item->update(['proof_status' => ProofStatus::Approved]);
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::ReadyToStart->value,
        ])->assertOk();

        $configuration->baseColor->update(['is_available' => false]);
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::InProduction->value,
        ])->assertUnprocessable()->assertJsonPath('errors.missing_materials.0.name', $configuration->baseColor->name);
        $this->assertTrue($item->fresh()->needs_attention);
        $this->actingAs($producer, 'sanctum')
            ->getJson('/api/admin/production-sheets?view=needs_attention')
            ->assertOk()
            ->assertJsonFragment(['code' => 'material_unavailable']);
        $configuration->baseColor->update(['is_available' => true]);

        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::InProduction->value,
        ])->assertOk();
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::Curing->value,
        ])->assertOk();
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::QualityCheck->value,
        ])->assertUnprocessable();

        $this->travel(13)->hours();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::QualityCheck->value,
        ])->assertOk();
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::Packed->value,
        ])->assertUnprocessable();

        $photoResponse = $this->actingAs($producer, 'sanctum')->post('/api/admin/order-items/'.$item->id.'/qc-photos', [
            'files' => [UploadedFile::fake()->image('qc.png', 300, 200)],
        ])->assertCreated();
        $photoId = $photoResponse->json('data.items.0.id');
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => ProductionStatus::Packed->value,
            'asset_ids' => [$photoId],
            'actual_labor_minutes' => 73,
        ])->assertOk();
        $item->refresh();
        $order->refresh();
        $this->assertSame(ProductionStatus::Packed, $item->production_status);
        $this->assertSame(73, $item->actual_labor_minutes);
        $this->assertSame(OrderStatus::AwaitingBalance, $order->status);
        Mail::assertQueued(BalanceRequestedMail::class, 1);

        $this->actingAs($customer, 'sanctum')->postJson('/api/orders/'.$order->id.'/payments', [
            'kind' => 'balance',
            'method' => 'wallet',
        ])->assertOk()->assertJsonPath('data.amount', $order->balance_amount);
        $balancePayment = $order->payments()->where('kind', 'balance')->firstOrFail();
        $this->settle($balancePayment);
        $this->assertSame(OrderStatus::ReadyForDelivery, $order->fresh()->status);

        $this->actingAs($producer, 'sanctum')->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => OrderStatus::OutForDelivery->value,
            'courier_name' => 'Courier',
            'courier_phone' => '+201000000000',
            'courier_reference' => 'R-1',
        ])->assertOk();
        $this->postJson('/api/admin/order-items/'.$item->id.'/transition', [
            'to' => OrderStatus::Delivered->value,
        ])->assertOk();
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);

        $timeline = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/timeline')
            ->assertOk();
        $this->assertEqualsCanonicalizing([
            'deposit_received',
            'balance_requested',
            'balance_received',
            'out_for_delivery',
            'delivered',
        ], array_column($timeline->json('data.events'), 'type'));
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(PaymentRecordStatus::Succeeded, $balancePayment->fresh()->status);
    }

    private function completeConfiguration(): StudioConfiguration
    {
        $baseItem = BaseItem::factory()->create([
            'base_price' => 12000,
            'labor_minutes' => 30,
            'cure_hours' => 12,
            'max_text_chars' => 50,
        ]);
        $mold = Mold::factory()->create(['base_item_id' => $baseItem->id, 'depth_mm' => 10]);
        $baseColor = ColorSwatch::factory()->create(['role' => ColorSwatchRole::Base]);
        $accentColor = ColorSwatch::factory()->create(['role' => ColorSwatchRole::Accent]);
        $technique = Technique::factory()->create(['requires_accent' => true]);
        $mood = Mood::factory()->create();
        $insert = Insert::factory()->create(['type' => InsertType::Flower, 'min_depth_mm' => 1]);
        $font = Font::factory()->create();
        $textColor = TextColor::factory()->create();

        return StudioConfiguration::fromArray([
            'base_item_id' => $baseItem->id,
            'mold_id' => $mold->id,
            'mood_id' => $mood->id,
            'technique_id' => $technique->id,
            'base_color_id' => $baseColor->id,
            'accent_color_id' => $accentColor->id,
            'insert_ids' => [$insert->id],
            'font_id' => $font->id,
            'text_color_id' => $textColor->id,
            'personalization_text' => 'For Sara',
        ]);
    }

    private function checkoutPayload(Quote $quote): array
    {
        return [
            'quote_id' => $quote->id,
            'shipping' => [
                'recipient_name' => 'Sara',
                'phone' => '+201234567890',
                'address_line1' => '1 Test Street',
                'city' => 'Tanta',
                'governorate' => 'Gharbia',
            ],
            'payment_method' => 'card',
            'acknowledgments' => [
                'handmade_variation' => true,
                'refund_policy' => true,
                'proof_auto_approval' => true,
            ],
        ];
    }

    private function settle(Payment $payment): void
    {
        $payload = [
            'reference' => $payment->provider_reference,
            'status' => PaymentRecordStatus::Succeeded->value,
            'amount' => $payment->amount,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/payments/webhook/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, 'production-test-secret'),
        ], $body)->assertOk();
    }
}
