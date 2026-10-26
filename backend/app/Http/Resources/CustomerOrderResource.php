<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => strip_tags($this->order_number),
            'status' => $this->status->value,
            'status_label' => $this->statusLabel($this->status->value),
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'price_breakdown' => $this->sanitize($this->price_breakdown),
            'shipping' => [
                'recipient_name' => strip_tags($this->shipping_name),
                'phone' => strip_tags($this->shipping_phone),
                'address_line1' => strip_tags($this->shipping_address_line1),
                'address_line2' => $this->shipping_address_line2 === null ? null : strip_tags($this->shipping_address_line2),
                'city' => strip_tags($this->shipping_city),
                'governorate' => strip_tags($this->shipping_governorate),
                'postal_code' => $this->shipping_postal_code === null ? null : strip_tags($this->shipping_postal_code),
                'country' => strip_tags($this->shipping_country),
            ],
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status->value,
            'payment_summary' => [
                'deposit_amount' => $this->deposit_amount,
                'balance_amount' => $this->balance_amount,
                'amount_paid' => $this->amount_paid,
                'due_now' => match ($this->status->value) {
                    'awaiting_deposit' => $this->deposit_amount,
                    'awaiting_balance' => $this->balance_amount,
                    default => 0,
                },
            ],
            'ready_by' => $this->ready_by->toIso8601String(),
            'accepted_terms_at' => $this->accepted_terms_at->toIso8601String(),
            'gift_options' => [
                'gift_wrap' => $this->gift_wrap,
                'gift_message' => $this->gift_message === null ? null : strip_tags($this->gift_message),
            ],
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'name' => strip_tags((string) ($item->customization['base_item']['name'] ?? 'Custom handmade item')),
                'unit_price' => $item->unit_price,
                'customization' => $this->customerCustomization($item->customization),
            ])->values(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function customerCustomization(array $snapshot): array
    {
        return $this->sanitize(array_intersect_key($snapshot, array_flip([
            'base_item',
            'mood',
            'color',
            'inserts',
            'personalization',
            'price',
        ])));
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $child) {
            if (in_array($key, ['schema_version', 'labor_minutes', 'labor_minutes_total', 'cure_hours', 'production_status', 'acknowledgments'], true)) {
                continue;
            }

            $clean[$key] = $this->sanitize($child);
        }

        return $clean;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'awaiting_deposit' => 'Awaiting deposit',
            'confirmed' => 'Confirmed',
            'awaiting_balance' => 'Awaiting balance',
            'ready_for_delivery' => 'Ready for delivery',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => 'Processing',
        };
    }
}
