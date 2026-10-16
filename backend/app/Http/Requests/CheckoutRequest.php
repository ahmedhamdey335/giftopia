<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
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
            'shipping.city' => ['required', 'string', 'max:120'],
            'shipping.governorate' => ['required', 'string', 'max:120'],
            'shipping.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping.country' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'gift_options' => ['sometimes', 'array'],
            'gift_options.gift_wrap' => ['sometimes', 'boolean'],
            'gift_options.gift_message' => ['nullable', 'string', 'max:500'],
            'acknowledgments' => ['required', 'array'],
            'acknowledgments.handmade_variation' => ['required', $this->mustBeTrue()],
            'acknowledgments.non_returnable' => ['required', $this->mustBeTrue()],
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
}
