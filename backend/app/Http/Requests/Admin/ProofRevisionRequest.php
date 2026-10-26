<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProofRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'font_id' => ['prohibited'],
            'text_color_id' => ['prohibited'],
            'font_name' => ['prohibited'],
            'text_color_name' => ['prohibited'],
        ];
    }
}
