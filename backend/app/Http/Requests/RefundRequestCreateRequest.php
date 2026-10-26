<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequestCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'evidence_asset_ids' => ['sometimes', 'array', 'max:3'],
            'evidence_asset_ids.*' => ['required', 'uuid'],
        ];
    }
}
