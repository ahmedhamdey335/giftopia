<?php

namespace Tests\Feature;

use App\Enums\ColorSwatchRole;
use App\Enums\InsertType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionEventType;
use App\Enums\ProductionStatus;
use App\Mail\OrderConfirmationMail;
use App\Models\Asset;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_an_immutable_snapshot_and_pending_deposit_payment(): void
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
            ->assertJsonPath('data.status', 'awaiting_deposit')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.total', $quote->total_price)
            ->assertJsonPath('data.ready_by', $quote->ready_at->toIso8601String())
            ->assertJsonPath('data.shipping.recipient_name', 'Alice')
            ->assertJsonPath('data.items.0.customization.personalization.note', 'Please center it')
            ->assertJsonMissingPath('data.items.0.labor_minutes_total')
            ->assertJsonMissingPath('data.items.0.customization.labor_minutes');
        $response->assertJsonPath('data.payment.amount', intdiv(($quote->total_price * 50) + 99, 100));
        $response->assertJsonPath('data.payment.expires_at', now()->addHours(24)->toIso8601String());

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
        $this->assertTrue($item->customization['acknowledgments']['refund_policy']['accepted']);
        $this->assertTrue($item->customization['acknowledgments']['proof_auto_approval']['accepted']);
        $this->assertCount(0, $order->productionEvents);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame($order->deposit_amount, $order->payments()->firstOrFail()->amount);
        $this->assertNotNull($quote->fresh()->used_at);
        Mail::assertNothingQueued();
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

    public function test_checkout_rejects_delivery_outside_tanta(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['shipping']['city'] = 'Cairo';

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'outside-delivery-area')
            ->postJson('/api/checkout', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping.city']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_trims_and_matches_delivery_locations_case_insensitively(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['shipping']['city'] = '  tAnTa ';
        $payload['shipping']['governorate'] = ' gHaRbIa ';
        unset($payload['shipping']['country']);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'trim-location')
            ->postJson('/api/checkout', $payload)
            ->assertCreated();

        $response->assertJsonPath('data.shipping.city', 'tAnTa')
            ->assertJsonPath('data.shipping.governorate', 'gHaRbIa')
            ->assertJsonPath('data.shipping.country', 'Egypt');
    }

    public function test_checkout_attaches_quoted_inspiration_assets_to_the_order_item(): void
    {
        $user = User::factory()->create();
        [$originalQuote, $configuration] = $this->makeQuote($user);
        $asset = Asset::query()->create([
            'kind' => 'inspiration',
            'owner_user_id' => $user->id,
            'disk' => 'private_assets',
            'path' => 'inspiration/checkout.png',
            'mime' => 'image/png',
            'size' => 100,
            'original_name' => 'checkout.png',
        ]);
        $withAsset = StudioConfiguration::fromArray([
            ...$configuration->normalizedConfiguration(),
            'inspiration_asset_ids' => [$asset->id],
        ]);
        $quote = app(StudioQuoteService::class)->create($withAsset, $user);
        $payload = $this->checkoutPayload($quote);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'attach-inspiration')
            ->postJson('/api/checkout', $payload)
            ->assertCreated();

        $item = Order::query()->firstOrFail()->items->firstOrFail();
        $this->assertSame($item->id, $asset->fresh()->order_item_id);
        $this->assertNotNull($asset->fresh()->attached_at);
        $this->assertSame([$asset->id], $item->customization['inspiration_assets']);
        $this->assertNotSame($originalQuote->config_hash, $quote->config_hash);
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
        $this->assertDatabaseCount('production_events', 0);
        $this->assertDatabaseCount('payments', 1);
        Mail::assertNothingQueued();
    }

    public function test_missing_acknowledgments_are_rejected_and_no_confirmation_is_queued(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        unset($payload['acknowledgments']['refund_policy']);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'missing-ack')
            ->postJson('/api/checkout', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['acknowledgments.refund_policy']);

        $this->assertDatabaseCount('orders', 0);
        Mail::assertNothingQueued();
    }

    public function test_proof_auto_approval_acknowledgment_is_only_required_for_personalized_orders(): void
    {
        $user = User::factory()->create();
        [, $configuration] = $this->makeQuote($user);
        $withoutText = StudioConfiguration::fromArray([
            ...$configuration->normalizedConfiguration(),
            'personalization_text' => null,
        ]);
        $quote = app(StudioQuoteService::class)->create($withoutText, $user);
        $payload = $this->checkoutPayload($quote);
        unset($payload['acknowledgments']['proof_auto_approval']);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'no-proof-needed')
            ->postJson('/api/checkout', $payload)
            ->assertCreated();

        $item = Order::query()->findOrFail($response->json('data.id'))->items->firstOrFail();
        $this->assertArrayNotHasKey('proof_auto_approval', $item->customization['acknowledgments']);
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

    public function test_fake_gateway_creates_card_deposit_payment_attempt(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['payment_method'] = PaymentMethod::Card->value;

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'online-fake')
            ->postJson('/api/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::AwaitingDeposit->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('fake', $payment->provider);
        $this->assertSame(PaymentMethod::Card, $payment->method);
        $this->assertSame($payment->amount, $response->json('data.payment.amount'));
    }

    public function test_deposit_webhook_confirms_order_once_and_queues_confirmation_after_success(): void
    {
        Mail::fake();
        config(['services.payments.fake_webhook_secret' => 'test-webhook-secret']);
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'deposit-webhook')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $payment = $order->payments()->firstOrFail();
        $payload = [
            'reference' => $payment->provider_reference,
            'status' => PaymentRecordStatus::Succeeded->value,
            'amount' => $payment->amount,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, 'test-webhook-secret'),
        ];

        $this->call('POST', '/api/payments/webhook/fake', [], [], [], $headers, $body)->assertOk();
        $order->refresh();
        $payment->refresh();

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(PaymentStatus::DepositPaid, $order->payment_status);
        $this->assertSame($payment->amount, $order->amount_paid);
        $this->assertSame(PaymentRecordStatus::Succeeded, $payment->status);
        $this->assertEquals($payment->paid_at->copy()->addDays(7), $order->ready_by);
        $this->assertDatabaseHas('production_events', [
            'order_id' => $order->id,
            'event_type' => ProductionEventType::DepositReceived->value,
        ]);
        Mail::assertQueued(OrderConfirmationMail::class, 1);

        $this->call('POST', '/api/payments/webhook/fake', [], [], [], $headers, $body)->assertOk();
        $this->assertSame($payment->amount, $order->fresh()->amount_paid);
        $this->assertDatabaseCount('production_events', 3);
        Mail::assertQueued(OrderConfirmationMail::class, 1);
    }

    public function test_forged_payment_webhook_is_rejected_without_changing_payment_state(): void
    {
        config(['services.payments.fake_webhook_secret' => 'test-webhook-secret']);
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'forged-webhook')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $payment = Payment::query()->firstOrFail();
        $body = json_encode([
            'reference' => $payment->provider_reference,
            'status' => 'succeeded',
            'amount' => $payment->amount,
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/payments/webhook/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => 'forged',
        ], $body)->assertUnauthorized();

        $this->assertSame(PaymentRecordStatus::Pending, $payment->fresh()->status);
        $this->assertSame(OrderStatus::AwaitingDeposit, $payment->order->fresh()->status);
    }

    public function test_unknown_payment_reference_is_acknowledged_without_side_effects(): void
    {
        config(['services.payments.fake_webhook_secret' => 'test-webhook-secret']);
        $body = json_encode([
            'reference' => 'unknown-reference',
            'status' => 'succeeded',
            'amount' => 1200,
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/payments/webhook/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, 'test-webhook-secret'),
        ], $body)->assertOk();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_deposit_hold_cancels_order_and_pending_attempt(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'expired-deposit')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $order = Order::query()->findOrFail($response->json('data.id'));
        $order->update(['deposit_expires_at' => now()->subSecond()]);

        $this->artisan('giftopia:expire-unpaid-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentRecordStatus::Cancelled, $order->payments()->firstOrFail()->fresh()->status);
    }

    public function test_balance_payment_is_rejected_before_order_awaits_balance(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'premature-balance')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();

        $this->postJson('/api/orders/'.$response->json('data.id').'/payments', [
            'kind' => 'balance',
            'method' => 'card',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_deposit_amount_uses_configured_delivery_fee_and_rounds_up(): void
    {
        config([
            'studio.delivery.fee' => 101,
            'studio.delivery.deposit_percent' => 33,
        ]);
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'deposit-rounding')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $total = $quote->total_price + 101;
        $expectedDeposit = intdiv(($total * 33) + 99, 100);

        $response->assertJsonPath('data.subtotal', $quote->total_price)
            ->assertJsonPath('data.delivery_fee', 101)
            ->assertJsonPath('data.total', $total)
            ->assertJsonPath('data.payment_summary.deposit_amount', $expectedDeposit)
            ->assertJsonPath('data.payment_summary.balance_amount', $total - $expectedDeposit)
            ->assertJsonPath('data.payment_summary.due_now', $expectedDeposit);
    }

    public function test_customer_can_reuse_open_deposit_attempt_and_cancel_before_payment(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $checkout = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'open-deposit')
            ->postJson('/api/checkout', $this->checkoutPayload($quote))
            ->assertCreated();
        $orderId = $checkout->json('data.id');
        $firstReference = Payment::query()->firstOrFail()->provider_reference;

        $this->postJson('/api/orders/'.$orderId.'/payments', [
            'kind' => 'deposit',
            'method' => 'card',
        ])->assertOk();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($firstReference, Payment::query()->firstOrFail()->provider_reference);

        $this->postJson('/api/orders/'.$orderId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);
        $this->assertSame(PaymentRecordStatus::Cancelled, Payment::query()->firstOrFail()->fresh()->status);
    }

    public function test_checkout_rejects_removed_payment_methods(): void
    {
        $user = User::factory()->create();
        [$quote] = $this->makeQuote($user);
        $payload = $this->checkoutPayload($quote);
        $payload['payment_method'] = 'cash_on_delivery';

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'no-cash')
            ->postJson('/api/checkout', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertDatabaseCount('orders', 0);
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
                'city' => 'Tanta',
                'governorate' => 'Gharbia',
                'postal_code' => '11511',
                'country' => 'Egypt',
            ],
            'payment_method' => PaymentMethod::Card->value,
            'gift_options' => [
                'gift_wrap' => true,
                'gift_message' => '<b>Happy birthday</b>',
            ],
            'acknowledgments' => [
                'handmade_variation' => true,
                'refund_policy' => true,
                'proof_auto_approval' => true,
            ],
        ];
    }
}
