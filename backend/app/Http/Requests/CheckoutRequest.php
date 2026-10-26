<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Quote;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $shipping = (array) $this->input('shipping', []);
        foreach (['city', 'governorate'] as $field) {
            if (isset($shipping[$field]) && is_string($shipping[$field])) {
                $shipping[$field] = trim($shipping[$field]);
            }
        }
        $shipping['country'] = $shipping['country'] ?? 'Egypt';

        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'shipping' => $shipping,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'shipping' => ['required', 'array'],
            'shipping.recipient_name' => ['required', 'string', 'max:120'],
            'shipping.phone' => ['required', 'string', 'max:32', 'regex:/^\\+?[0-9\\s().-]{7,32}$/'],
            'shipping.address_line1' => ['required', 'string', 'max:255'],
            'shipping.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping.city' => [
                'required',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->matchesConfiguredLocation($value, 'cities')) {
                        $fail('Delivery is currently available only in Tanta, Gharbia.');
                    }
                },
            ],
            'shipping.governorate' => [
                'required',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->matchesConfiguredLocation($value, 'governorates')) {
                        $fail('Delivery is currently available only in Tanta, Gharbia.');
                    }
                },
            ],
            'shipping.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping.country' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'gift_options' => ['sometimes', 'array'],
            'gift_options.gift_wrap' => ['sometimes', 'boolean'],
            'gift_options.gift_message' => ['nullable', 'string', 'max:500'],
            'acknowledgments' => ['required', 'array'],
            'acknowledgments.handmade_variation' => ['required', $this->mustBeTrue()],
            'acknowledgments.refund_policy' => ['required', $this->mustBeTrue()],
            'acknowledgments.proof_auto_approval' => [
                Rule::requiredIf(fn (): bool => $this->quoteHasPersonalization()),
                $this->mustBeTrue(),
            ],
        ];
    }

    private function mustBeTrue(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== true) {
                $fail('The acknowledgment must be accepted.');
            }
        };
    }

    private function matchesConfiguredLocation(mixed $value, string $setting): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $configured = array_map(
            static fn (string $name): string => mb_strtolower(trim($name)),
            (array) config('studio.delivery.'.$setting, []),
        );

        return in_array(mb_strtolower(trim($value)), $configured, true);
    }

    private function quoteHasPersonalization(): bool
    {
        $quoteId = $this->input('quote_id');
        if (! is_string($quoteId)) {
            return false;
        }

        $quote = Quote::query()->find($quoteId);

        return filled($quote?->configuration['personalization_text'] ?? null);
    }
}
