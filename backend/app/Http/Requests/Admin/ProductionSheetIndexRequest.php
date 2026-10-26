<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionSheetIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'view' => ['sometimes', Rule::in(['today', 'waiting', 'needs_attention', 'shipping', 'all'])],
            'status' => ['sometimes', Rule::in(array_column(ProductionStatus::cases(), 'value'))],
            'due_from' => ['sometimes', 'date'],
            'due_to' => ['sometimes', 'date', 'after_or_equal:due_from'],
            'q' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
