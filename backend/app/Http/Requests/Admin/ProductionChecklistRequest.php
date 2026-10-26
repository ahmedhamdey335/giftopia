<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductionChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'checks' => ['required', 'array'],
            'checks.steps' => ['sometimes', 'array'],
            'checks.steps.*' => ['boolean'],
            'checks.materials' => ['sometimes', 'array'],
            'checks.materials.*' => ['boolean'],
        ];
    }
}
