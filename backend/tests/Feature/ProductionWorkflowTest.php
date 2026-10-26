<?php

namespace Tests\Feature;

use App\Enums\ColorSwatchRole;
use App\Enums\InsertType;
use App\Enums\OrderStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use App\Enums\ProofStatus;
use App\Enums\RefundRequestStatus;
use App\Mail\BalanceRequestedMail;
use App\Mail\ProofNotificationMail;
use App\Models\BaseItem;
use App\Models\BlackoutDate;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\StudioEvent;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use App\Services\Production\AttentionService;
use App\Services\Production\ProofService;
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
            'proof_requested',
            'balance_requested',
            'balance_received',
            'out_for_delivery',
            'delivered',
        ], array_column($timeline->json('data.events'), 'type'));
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(PaymentRecordStatus::Succeeded, $balancePayment->fresh()->status);

        $additionalOrders = [];
        $producerConfiguration = [
            ...$configuration->normalizedConfiguration(),
            'font_id' => null,
            'text_color_id' => null,
            'personalization_text' => null,
            'personalization_note' => null,
        ];
        for ($index = 1; $index <= 4; $index++) {
            $nextCustomer = User::factory()->create();
            $nextQuote = app(StudioQuoteService::class)->create(
                StudioConfiguration::fromArray($producerConfiguration),
                $nextCustomer,
            );
            $nextResponse = $this->actingAs($nextCustomer, 'sanctum')
                ->withHeader('Idempotency-Key', 'production-order-'.$index)
                ->postJson('/api/checkout', $this->checkoutPayload($nextQuote))
                ->assertCreated();
            $nextOrder = Order::query()->findOrFail($nextResponse->json('data.id'));
            $nextItem = $nextOrder->items()->firstOrFail();
            $this->settle($nextOrder->payments()->firstOrFail());
            $this->actingAs($producer, 'sanctum')
                ->getJson('/api/admin/production-sheets/'.$nextItem->id)
                ->assertOk()
                ->assertJsonPath('data.look', $configuration->mood->name)
                ->assertJsonPath('data.text', null);
            $this->assertGreaterThan(0, count($this->getJson('/api/admin/production-sheets/'.$nextItem->id)->json('data.materials_check')));
            $additionalOrders[] = ['customer' => $nextCustomer, 'order' => $nextOrder, 'item' => $nextItem];
        }
        $batches = $this->actingAs($producer, 'sanctum')
            ->getJson('/api/admin/production-sheets/batches')
            ->assertOk();
        $this->assertCount(1, $batches->json('data.batches'));
        $this->assertCount(4, $batches->json('data.batches.0.items'));

        foreach ($additionalOrders as $entry) {
            $itemId = $entry['item']->id;
            $this->actingAs($producer, 'sanctum')
                ->postJson('/api/admin/order-items/'.$itemId.'/transition', ['to' => ProductionStatus::InProduction->value])->assertOk();
            $this->postJson('/api/admin/order-items/'.$itemId.'/transition', ['to' => ProductionStatus::Curing->value])->assertOk();
        }
        $this->travel(13)->hours();
        foreach ($additionalOrders as $entry) {
            $itemId = $entry['item']->id;
            $this->actingAs($producer, 'sanctum');
            $this->postJson('/api/admin/order-items/'.$itemId.'/transition', ['to' => ProductionStatus::QualityCheck->value])->assertOk();
            $photo = $this->post('/api/admin/order-items/'.$itemId.'/qc-photos', [
                'files' => [UploadedFile::fake()->image('batch-qc.png', 300, 200)],
            ])->assertCreated();
            $this->postJson('/api/admin/order-items/'.$itemId.'/transition', [
                'to' => ProductionStatus::Packed->value,
                'asset_ids' => [$photo->json('data.items.0.id')],
            ])->assertOk();
            $nextOrder = $entry['order'];
            $balance = $this->actingAs($entry['customer'], 'sanctum')
                ->postJson('/api/orders/'.$nextOrder->id.'/payments', ['kind' => 'balance', 'method' => 'card'])
                ->assertOk();
            $this->settle($nextOrder->payments()->where('kind', 'balance')->latest('id')->firstOrFail());
            $this->actingAs($producer, 'sanctum')->postJson('/api/admin/order-items/'.$itemId.'/transition', [
                'to' => OrderStatus::OutForDelivery->value,
            ])->assertOk();
            $this->postJson('/api/admin/order-items/'.$itemId.'/transition', [
                'to' => OrderStatus::Delivered->value,
            ])->assertOk();
            $this->actingAs($entry['customer'], 'sanctum')->getJson('/api/orders/'.$nextOrder->id)
                ->assertOk()->assertJsonPath('data.status', 'delivered');
        }
        $this->assertSame(5, Order::query()->count());
    }

    public function test_customer_proof_revision_approval_and_reorder_use_current_catalog(): void
    {
        Mail::fake();
        config(['services.payments.fake_webhook_secret' => 'production-test-secret']);
        $customer = User::factory()->create();
        $producer = User::factory()->create(['role' => 'producer']);
        $configuration = $this->completeConfiguration();
        $quote = app(StudioQuoteService::class)->create($configuration, $customer);
        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'proof-reorder')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $item = $order->items()->firstOrFail();
        $this->settle($order->payments()->firstOrFail());

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$order->id.'/proof')
            ->assertOk()
            ->assertJsonPath('data.items.0.version', 1)
            ->assertJsonPath('data.items.0.status', 'pending');
        $this->postJson('/api/orders/'.$order->id.'/proof/request-changes', ['note' => 'Please use a shorter line.'])
            ->assertOk();
        $this->actingAs($producer, 'sanctum')
            ->postJson('/api/admin/order-items/'.$item->id.'/proof/revise', [
                'text' => 'For Sara!',
                'note' => 'Updated punctuation',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);
        $this->actingAs($customer, 'sanctum');
        $this->postJson('/api/orders/'.$order->id.'/proof/approve')->assertOk();
        $this->assertSame(ProductionStatus::ReadyToStart, $item->fresh()->production_status);
        $this->actingAs($producer, 'sanctum')
            ->getJson('/api/admin/production-sheets/'.$item->id)
            ->assertOk()
            ->assertJsonPath('data.text.value', 'For Sara!')
            ->assertJsonPath('data.text.proof_version', 2);
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders/'.$order->id.'/reorder')
            ->assertCreated()
            ->assertJsonPath('data.draft.inspiration_asset_ids', [])
            ->assertJsonPath('data.draft.schema_version', 1);

        $configuration->baseColor->update(['is_available' => false]);
        $this->postJson('/api/orders/'.$order->id.'/reorder')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['issues', 'draft']]);
    }

    public function test_schedule_skips_blackouts_and_flags_capacity_risk(): void
    {
        Mail::fake();
        config(['services.payments.fake_webhook_secret' => 'production-test-secret']);
        $this->travelTo(now()->setTime(10, 0));
        config(['studio.daily_capacity_minutes' => 10, 'studio.delivery.lead_time_days' => 1]);
        BlackoutDate::query()->create(['date' => now()->toDateString(), 'reason' => 'Workshop closed']);
        $customer = User::factory()->create();
        $configuration = $this->completeConfiguration();
        $quote = app(StudioQuoteService::class)->create($configuration, $customer);
        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'schedule-blackout')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $item = $order->items()->firstOrFail();
        $this->settle($order->payments()->firstOrFail());

        $blackout = BlackoutDate::query()->firstOrFail()->date;
        $this->assertSame(now()->toDateString(), $blackout->setTimezone(config('studio.timezone'))->toDateString());
        $this->assertSame(now()->addDay()->toDateString(), $item->fresh()->planned_start->toDateString());
        $this->assertContains(
            'at_risk',
            array_column(app(AttentionService::class)->reasons($item->fresh()), 'code'),
        );
    }

    public function test_refund_evidence_claim_window_and_partial_then_full_refund(): void
    {
        Mail::fake();
        Storage::fake('private_assets');
        config(['services.payments.fake_webhook_secret' => 'production-test-secret']);
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $configuration = $this->completeConfiguration();
        $quote = app(StudioQuoteService::class)->create($configuration, $customer);
        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'refund-e2e')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $this->settle($order->payments()->firstOrFail());
        $order->update(['status' => OrderStatus::AwaitingBalance]);

        $upload = $this->actingAs($customer, 'sanctum')->post('/api/refund-evidence', [
            'order_id' => $order->id,
            'files' => [UploadedFile::fake()->image('evidence.jpg', 200, 200)],
        ])->assertCreated();
        $evidenceId = $upload->json('data.items.0.id');
        $this->postJson('/api/orders/'.$order->id.'/refund-requests', [
            'reason' => 'The finished piece does not match the approved design.',
            'evidence_asset_ids' => [$evidenceId],
        ])->assertCreated()->assertJsonPath('data.status', 'requested');
        $this->postJson('/api/orders/'.$order->id.'/refund-requests', [
            'reason' => 'A second request should be blocked while the first is open.',
        ])->assertStatus(409);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/refund-requests?status=requested')
            ->assertOk()
            ->assertJsonPath('data.items.0.evidence.0.id', $evidenceId)
            ->assertJsonStructure(['data' => ['items' => [['approved_proofs', 'qc_photos']]]]);
        $refundId = $order->refundRequests()->firstOrFail()->id;
        $partial = max(1, (int) floor($order->amount_paid / 2));
        config(['services.payments.gateway' => 'paymob']);
        $this->postJson('/api/admin/refund-requests/'.$refundId.'/approve', [
            'approved_amount' => $partial,
        ])->assertOk()->assertJsonPath('data.status', 'failed');
        $this->assertSame(PaymentStatus::DepositPaid, $order->fresh()->payment_status);
        $this->assertContains(
            'refund_request',
            array_column(app(AttentionService::class)->reasons($order->items()->firstOrFail()), 'code'),
        );
        config(['services.payments.gateway' => 'fake']);
        $this->postJson('/api/admin/refund-requests/'.$refundId.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);

        $this->actingAs($customer, 'sanctum')->postJson('/api/orders/'.$order->id.'/refund-requests', [
            'reason' => 'The remaining paid amount should be refundable too.',
        ])->assertCreated();
        $secondRefund = $order->refundRequests()->latest('id')->firstOrFail();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/refund-requests/'.$secondRefund->id.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertSame(RefundRequestStatus::Refunded, $secondRefund->fresh()->status);

        $order->update([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now()->subDays(4),
        ]);
        $this->actingAs($customer, 'sanctum')->postJson('/api/orders/'.$order->id.'/refund-requests', [
            'reason' => 'This claim is outside the configured delivery window.',
        ])->assertUnprocessable();
    }

    public function test_proof_reminder_and_auto_approval_are_scheduled_idempotently(): void
    {
        Mail::fake();
        config([
            'services.payments.fake_webhook_secret' => 'production-test-secret',
            'studio.delivery.proof_auto_approve_hours' => 48,
        ]);
        $customer = User::factory()->create();
        $configuration = $this->completeConfiguration();
        $quote = app(StudioQuoteService::class)->create($configuration, $customer);
        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'proof-auto')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $item = $order->items()->firstOrFail();
        $this->settle($order->payments()->firstOrFail());

        $this->travel(25)->hours();
        $this->artisan('giftopia:process-proofs')->assertExitCode(0);
        $this->assertDatabaseHas('proofs', ['order_item_id' => $item->id, 'status' => 'pending']);
        Mail::assertQueued(ProofNotificationMail::class, fn (ProofNotificationMail $mail): bool => $mail->notificationType === 'reminder');

        $this->travel(24)->hours();
        $this->artisan('giftopia:process-proofs')->assertExitCode(0);
        $this->assertSame(ProofStatus::Approved, $item->fresh()->proof_status);
        $this->assertDatabaseHas('proofs', [
            'order_item_id' => $item->id,
            'status' => 'approved',
            'response_note' => 'auto-approved',
        ]);
        $this->artisan('giftopia:process-proofs')->assertExitCode(0);
        Mail::assertQueued(ProofNotificationMail::class, fn (ProofNotificationMail $mail): bool => $mail->notificationType === 'auto_approved');
    }

    public function test_guest_funnel_events_and_admin_reports_include_metrics_and_zero_values(): void
    {
        Mail::fake();
        config([
            'services.payments.fake_webhook_secret' => 'production-test-secret',
            'studio.reporting.minimum_samples' => 1,
            'studio.reporting.variance_threshold_percent' => 15,
        ]);
        $from = now()->toDateString();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/overview?from='.$from.'&to='.$from)
            ->assertOk()
            ->assertJsonPath('data.orders_created', 0)
            ->assertJsonPath('data.deposit_paid_rate_percent', 0)
            ->assertJsonPath('data.on_time_rate_percent', 0)
            ->assertJsonPath('data.funnel.0.completion_rate_percent', 0);
        $this->postJson('/api/studio/events', [
            'events' => [
                ['session_id' => '2e781ddb-3a10-4a2d-939f-2d203a1bf923', 'step' => 1, 'event' => 'viewed'],
                ['session_id' => '2e781ddb-3a10-4a2d-939f-2d203a1bf923', 'step' => 1, 'event' => 'completed'],
                ['session_id' => '2e781ddb-3a10-4a2d-939f-2d203a1bf923', 'step' => 2, 'event' => 'viewed'],
                ['session_id' => '2e781ddb-3a10-4a2d-939f-2d203a1bf923', 'step' => 2, 'event' => 'abandoned'],
            ],
        ])->assertAccepted()->assertJsonPath('data.accepted', 4);

        $customer = User::factory()->create();
        $configuration = $this->completeConfiguration();
        $quote = app(StudioQuoteService::class)->create($configuration, $customer);
        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'report-seed')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $item = $order->items()->firstOrFail();
        $this->settle($order->payments()->firstOrFail());
        $item->update(['actual_labor_minutes' => $item->labor_minutes_total * 2]);
        $order->update([
            'status' => OrderStatus::ReadyForDelivery,
            'ready_for_delivery_at' => now(),
        ]);
        app(ProofService::class)->approve($item, $admin);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/overview?from='.$from.'&to='.$from)
            ->assertOk()
            ->assertJsonPath('data.orders_created', 1)
            ->assertJsonPath('data.deposit_paid_rate_percent', 100)
            ->assertJsonPath('data.proofs_approved_first_version_percent', 100)
            ->assertJsonPath('data.on_time_rate_percent', 100)
            ->assertJsonPath('data.funnel.0.completion_rate_percent', 100)
            ->assertJsonPath('data.funnel.1.abandonment_rate_percent', 100);
        $this->getJson('/api/admin/reports/estimates?from='.$from.'&to='.$from)
            ->assertOk()
            ->assertJsonPath('data.base_items.0.suggest_adjustment', true)
            ->assertJsonPath('data.techniques.0.suggest_adjustment', true);
        $this->assertSame(4, StudioEvent::query()->count());
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
