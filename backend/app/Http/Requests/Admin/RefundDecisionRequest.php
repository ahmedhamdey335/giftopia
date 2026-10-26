<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefundDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => ['sometimes', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
