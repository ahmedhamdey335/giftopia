<?php

namespace Tests\Feature;

use App\Enums\ColorSwatchRole;
use App\Enums\InsertType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductionEventType;
use App\Enums\ProductionStatus;
use App\Mail\OrderConfirmationMail;
use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Studio\StudioConfiguration;
use App\Services\Studio\StudioQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_an_immutable_snapshot_order_and_queues_confirmation(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$quote, $configuration] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['shipping']['recipient_name'] = '<b>Alice</b>';
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'checkout-happy-path')
            ->postJson('/api/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'placed')
            ->assertJsonPath('data.total', $quote->total_price)
            ->assertJsonPath('data.ready_by', $quote->ready_at->toIso8601String())
            ->assertJsonPath('data.shipping.recipient_name', 'Alice')
            ->assertJsonPath('data.items.0.customization.personalization.note', 'Please center it')
            ->assertJsonMissingPath('data.items.0.labor_minutes_total')
            ->assertJsonMissingPath('data.items.0.customization.labor_minutes');

        $order = Order::query()->with('items', 'productionEvents')->firstOrFail();
        $item = $order->items->firstOrFail();
        $this->assertSame($quote->total_price, $order->price_breakdown['total']);
        $this->assertSame($quote->total_price, $item->unit_price);
        $this->assertSame($configuration->mood->id, $item->mood_id);
        $this->assertSame($configuration->technique->id, $item->technique_id);
        $this->assertTrue($item->has_text);
        $this->assertSame(ProductionStatus::Placed, $item->production_status);
        $this->assertSame(1, $item->customization['schema_version']);
        $this->assertTrue($item->customization['acknowledgments']['handmade_variation']['accepted']);
        $this->assertNotNull($item->customization['acknowledgments']['handmade_variation']['accepted_at']);
        $this->assertSame(ProductionEventType::Placed, $order->productionEvents->first()->event_type);
        $this->assertNotNull($quote->fresh()->used_at);

        $readyDate = $order->ready_by->setTimezone('Africa/Cairo')->format('F j, Y');
        Mail::assertQueued(OrderConfirmationMail::class, fn (OrderConfirmationMail $mail): bool => $mail->order->order_number === $response->json('data.order_number')
            && $mail->content()->with['readyDate'] === $readyDate);
    }

    public function test_checkout_ignores_client_supplied_prices_and_customization(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote) + [
            'total' => 1,
            'unit_price' => 1,
            'price_breakdown' => ['total' => 1],
            'customization' => ['base_item' => ['name' => 'Tampered']],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'ignore-client-price')
            ->postJson('/api/checkout', $payload)
            ->assertCreated();

        $this->assertSame($quote->total_price, $response->json('data.total'));
        $this->assertNotSame('Tampered', Order::query()->firstOrFail()->items->first()->customization['base_item']['name']);
    }

    public function test_expired_used_and_foreign_quotes_are_rejected_without_creating_orders(): void
    {
        $user = User::factory()->create();
        [$expired] = $this->makeQuote($user);
        $expired->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'expired-quote')
            ->postJson('/api/checkout', $this->checkoutPayload($expired))
            ->assertUnprocessable();

        [$used] = $this->makeQuote($user);
        $used->update(['used_at' => now()]);
        $this->withHeader('Idempotency-Key', 'used-quote')
            ->postJson('/api/checkout', $this->checkoutPayload($used))
            ->assertStatus(409);

        $owner = User::factory()->create();
        [$foreign] = $this->makeQuote($owner);
        $this->withHeader('Idempotency-Key', 'foreign-quote')
            ->postJson('/api/checkout', $this->checkoutPayload($foreign))
            ->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_claims_a_guest_quote_for_the_authenticated_customer(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote(null);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'claim-guest-quote')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();

        $this->assertSame($user->id, $quote->fresh()->user_id);
    }

    public function test_checkout_rejects_unavailable_catalog_options_and_does_not_queue_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$quote, $configuration] = $this->makeQuote($user);
        $configuration->baseColor->update(['is_available' => false]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'unavailable-option')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'That base pigment is out of stock.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertNull($quote->fresh()->used_at);
        Mail::assertNothingQueued();
    }

    public function test_price_drift_returns_a_fresh_quote_for_reconfirmation(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$quote, $configuration] = $this->makeQuote($user);
        $oldTotal = $quote->total_price;
        $configuration->technique->update(['price_delta' => $configuration->technique->price_delta + 1000]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'reconfirm-price')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertStatus(409);

        $newQuote = Quote::query()->findOrFail($response->json('errors.quote.quote_id'));
        $this->assertGreaterThan($oldTotal, $newQuote->total_price);
        $this->assertNotNull($quote->fresh()->used_at);
        $this->assertDatabaseCount('orders', 0);
        Mail::assertNothingQueued();
    }

    public function test_catalog_edits_after_checkout_do_not_change_the_snapshot(): void
    {
        $user = User::factory()->create();
        [$quote, $configuration] = $this->makeQuote($user);
        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'snapshot-immutable')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $item = Order::query()->firstOrFail()->items->firstOrFail();
        $snapshot = $item->customization;

        $configuration->baseItem->update(['name' => 'Renamed Item', 'base_price' => 1]);
        $configuration->technique->update(['name' => 'Renamed Technique', 'price_delta' => 1]);

        $this->assertSame($snapshot, $item->fresh()->customization);
        $this->assertSame($quote->total_price, $item->fresh()->unit_price);
        $this->actingAs($user, 'sanctum')->getJson('/api/orders/'.$item->order_id)
            ->assertOk()
            ->assertJsonPath('data.items.0.name', $snapshot['base_item']['name']);
    }

    public function test_idempotency_returns_the_original_order_without_duplicate_items_or_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);

        $first = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-mobile-submit')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'same-mobile-submit')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('production_events', 1);
        Mail::assertQueued(OrderConfirmationMail::class, 1);
    }

    public function test_missing_acknowledgments_are_rejected_and_no_confirmation_is_queued(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        unset($payload['acknowledgments']['non_returnable']);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'missing-ack')
            ->postJson('/api/checkout', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['acknowledgments.non_returnable']);

        $this->assertDatabaseCount('orders', 0);
        Mail::assertNothingQueued();
    }

    public function test_acknowledgments_must_be_boolean_true(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['acknowledgments']['handmade_variation'] = 'yes';

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'strict-acknowledgment')
            ->postJson('/api/checkout', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['acknowledgments.handmade_variation']);
    }

    public function test_fake_gateway_can_place_an_online_order_in_awaiting_payment_status(): void
    {
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['payment_method'] = PaymentMethod::Online->value;

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'online-fake')
            ->postJson('/api/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::AwaitingPayment->value)
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_customers_can_only_list_and_view_their_own_orders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$ownerQuote] = $this->makeQuote($owner);
        [$otherQuote] = $this->makeQuote($other);

        $ownerResponse = $this->actingAs($owner, 'sanctum')
            ->withHeader('Idempotency-Key', 'owner-order')
            ->postJson('/api/checkout', $this->checkoutPayload($ownerQuote))
            ->assertCreated();
        $otherResponse = $this->actingAs($other, 'sanctum')
            ->withHeader('Idempotency-Key', 'other-order')
            ->postJson('/api/checkout', $this->checkoutPayload($otherQuote))
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $ownerResponse->json('data.id'));
        $this->getJson('/api/orders/'.$otherResponse->json('data.id'))->assertForbidden();
    }

    private function makeQuote(?User $user): array
    {
        $base = BaseItem::factory()->create([
            'base_price' => 12345,
            'labor_minutes' => 30,
            'cure_hours' => 12,
            'max_text_chars' => 50,
        ]);
        $mold = Mold::factory()->create([
            'base_item_id' => $base->id,
            'depth_mm' => 10,
            'price_delta' => 1200,
        ]);
        $mood = Mood::factory()->create();
        $baseColor = ColorSwatch::factory()->create([
            'role' => ColorSwatchRole::Base,
            'price_delta' => 100,
        ]);
        $accentColor = ColorSwatch::factory()->create([
            'role' => ColorSwatchRole::Accent,
            'price_delta' => 200,
        ]);
        $technique = Technique::factory()->create([
            'price_delta' => 300,
            'labor_minutes' => 15,
            'requires_accent' => true,
        ]);
        $insert = Insert::factory()->create([
            'type' => InsertType::Flower,
            'price' => 400,
            'labor_minutes' => 10,
            'min_depth_mm' => 5,
        ]);
        $font = Font::factory()->create(['price_delta' => 50]);
        $textColor = TextColor::factory()->create(['price_delta' => 60]);
        $configuration = StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'mold_id' => $mold->id,
            'mood_id' => $mood->id,
            'technique_id' => $technique->id,
            'base_color_id' => $baseColor->id,
            'accent_color_id' => $accentColor->id,
            'insert_ids' => [$insert->id],
            'font_id' => $font->id,
            'text_color_id' => $textColor->id,
            'personalization_text' => 'Hello',
            'personalization_note' => '<b>Please center it</b>',
        ]);

        return [app(StudioQuoteService::class)->create($configuration, $user), $configuration];
    }

    private function checkoutPayload(Quote $quote): array
    {
        return [
            'quote_id' => $quote->id,
            'shipping' => [
                'recipient_name' => 'Alice Customer',
                'phone' => '+201234567890',
                'address_line1' => '10 Example Street',
                'address_line2' => null,
                'city' => 'Cairo',
                'governorate' => 'Cairo',
                'postal_code' => '11511',
                'country' => 'Egypt',
            ],
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'gift_options' => [
                'gift_wrap' => true,
                'gift_message' => '<b>Happy birthday</b>',
            ],
            'acknowledgments' => [
                'handmade_variation' => true,
                'non_returnable' => true,
            ],
        ];
    }
}
