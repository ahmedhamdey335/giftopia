<?php

namespace App\Services\Checkout;

use App\Enums\ProductionEventType;
use App\Enums\ProductionStatus;
use App\Exceptions\CheckoutException;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use App\Services\CompatibilityValidator;
use App\Services\Payments\PaymentGateway;
use App\Services\Studio\PriceCalculator;
use App\Services\Studio\SnapshotBuilder;
use App\Services\Studio\StudioConfiguration;
use App\Services\Studio\StudioQuoteService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class CheckoutService
{
    public function __construct(
        private readonly CompatibilityValidator $compatibility,
        private readonly PriceCalculator $prices,
        private readonly SnapshotBuilder $snapshots,
        private readonly PaymentGateway $payments,
        private readonly StudioQuoteService $quotes,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function checkout(User $user, array $input): CheckoutResult
    {
        $key = $input['idempotency_key'];

        try {
            return DB::transaction(function () use ($user, $input, $key): CheckoutResult {
                $existing = Order::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $key)
                    ->with('items')
                    ->first();
                if ($existing) {
                    return CheckoutResult::order($existing, existing: true);
                }

                $quote = Quote::query()
                    ->whereKey($input['quote_id'])
                    ->lockForUpdate()
                    ->first();
                if (! $quote) {
                    throw new CheckoutException('Quote not found.', 404);
                }

                if ($quote->isExpired()) {
                    throw new CheckoutException('This quote has expired.', 422);
                }

                if ($quote->used_at !== null) {
                    throw new CheckoutException('This quote has already been used.', 409);
                }

                if ($quote->user_id !== null && $quote->user_id !== $user->id) {
                    throw new CheckoutException('This quote belongs to another customer.', 403);
                }

                $acceptedAt = now();
                $quote->user_id = $user->id;
                $quote->used_at = $acceptedAt;
                $quote->save();

                $configuration = StudioConfiguration::fromArray($quote->configuration);
                $normalized = $configuration->normalizedConfiguration();
                $currentHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
                if (! hash_equals((string) $quote->config_hash, $currentHash)) {
                    throw new CheckoutException('This quote configuration is no longer valid.', 409);
                }

                $compatibility = $this->compatibility->validate($configuration, strict: true);
                if (! $compatibility->allowed) {
                    throw new CheckoutException('The quoted configuration is no longer available.', 422, [
                        'violations' => $compatibility->violations,
                    ]);
                }

                $price = $this->prices->calculate($configuration);
                if ($price['total'] !== $quote->total_price || ! $this->priceBreakdownsMatch($price, $quote->price_breakdown)) {
                    return CheckoutResult::quote($this->quotes->create($configuration, $user));
                }

                $payment = $this->payments->initialize($input['payment_method']);
                $shipping = $input['shipping'];
                $giftOptions = $input['gift_options'] ?? [];
                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'order_number' => 'GFT-'.$acceptedAt->format('ymd').'-'.Str::upper(Str::random(8)),
                    'status' => $payment->orderStatus,
                    'currency' => (string) config('studio.currency', 'EGP'),
                    'subtotal' => $price['total'],
                    'total' => $price['total'],
                    'price_breakdown' => $price,
                    'shipping_name' => $shipping['recipient_name'],
                    'shipping_phone' => $shipping['phone'],
                    'shipping_address_line1' => $shipping['address_line1'],
                    'shipping_address_line2' => $shipping['address_line2'] ?? null,
                    'shipping_city' => $shipping['city'],
                    'shipping_governorate' => $shipping['governorate'],
                    'shipping_postal_code' => $shipping['postal_code'] ?? null,
                    'shipping_country' => $shipping['country'],
                    'payment_method' => $input['payment_method'],
                    'payment_status' => $payment->paymentStatus,
                    'ready_by' => $quote->ready_at,
                    'accepted_terms_at' => $acceptedAt,
                    'gift_wrap' => (bool) ($giftOptions['gift_wrap'] ?? false),
                    'gift_message' => $giftOptions['gift_message'] ?? null,
                    'idempotency_key' => $key,
                ]);

                $customization = $this->snapshots->build($configuration, $price);
                $customization['acknowledgments'] = [
                    'handmade_variation' => [
                        'accepted' => true,
                        'accepted_at' => $acceptedAt->toIso8601String(),
                    ],
                    'non_returnable' => [
                        'accepted' => true,
                        'accepted_at' => $acceptedAt->toIso8601String(),
                    ],
                ];

                $order->items()->create([
                    'mood_id' => $configuration->mood?->id,
                    'technique_id' => $configuration->technique?->id,
                    'has_text' => $configuration->hasPersonalization(),
                    'labor_minutes_total' => $configuration->totalLaborMinutes(),
                    'unit_price' => $price['total'],
                    'production_status' => ProductionStatus::Placed,
                    'customization' => $customization,
                ]);
                $order->productionEvents()->create([
                    'actor_id' => $user->id,
                    'event_type' => ProductionEventType::Placed,
                ]);

                $orderId = $order->id;
                DB::afterCommit(static function () use ($orderId): void {
                    $committedOrder = Order::query()->with('items')->findOrFail($orderId);
                    Mail::to($committedOrder->user->email)
                        ->queue((new OrderConfirmationMail($committedOrder))->afterCommit());
                });

                return CheckoutResult::order($order->load('items'));
            });
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyConflict($exception)) {
                throw $exception;
            }

            $existing = Order::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $key)
                ->with('items')
                ->first();
            if ($existing) {
                return CheckoutResult::order($existing, existing: true);
            }

            throw $exception;
        }
    }

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'orders_user_idempotency_unique')
            || str_contains($message, 'orders.user_id, orders.idempotency_key');
    }

    /**
     * @param  array{items: list<array<string, mixed>>, total: int, currency: string}  $current
     * @param  array<string, mixed>  $quoted
     */
    private function priceBreakdownsMatch(array $current, array $quoted): bool
    {
        if (($quoted['total'] ?? null) !== $current['total']
            || ($quoted['currency'] ?? null) !== $current['currency']
            || ! is_array($quoted['items'] ?? null)
            || count($quoted['items']) !== count($current['items'])) {
            return false;
        }

        foreach ($current['items'] as $index => $item) {
            $quotedItem = $quoted['items'][$index] ?? null;
            if (! is_array($quotedItem)
                || ($quotedItem['key'] ?? null) !== $item['key']
                || (int) ($quotedItem['id'] ?? 0) !== $item['id']
                || (int) ($quotedItem['amount'] ?? -1) !== $item['amount']) {
                return false;
            }
        }

        return true;
    }
}
