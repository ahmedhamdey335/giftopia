<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $states = [
            ...array_column(ProductionStatus::cases(), 'value'),
            OrderStatus::OutForDelivery->value,
            OrderStatus::Delivered->value,
        ];

        return [
            'to' => ['required', 'string', Rule::in($states)],
            'note' => ['nullable', 'string', 'max:255'],
            'override' => ['sometimes', 'boolean'],
            'asset_ids' => ['sometimes', 'array', 'max:3'],
            'asset_ids.*' => ['uuid', 'distinct', 'exists:assets,id'],
            'actual_labor_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'courier_phone' => ['nullable', 'string', 'max:32'],
            'courier_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
