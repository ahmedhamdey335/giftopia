<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundEvidenceUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'files' => ['required', 'array', 'min:1', 'max:3'],
            'files.*' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }
}
