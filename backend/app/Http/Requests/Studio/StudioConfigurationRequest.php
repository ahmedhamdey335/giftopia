<?php

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;

class StudioConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_item_id' => ['nullable', 'integer', 'min:1'],
            'mold_id' => ['nullable', 'integer', 'min:1'],
            'mood_id' => ['nullable', 'integer', 'min:1'],
            'technique_id' => ['nullable', 'integer', 'min:1'],
            'base_color_id' => ['nullable', 'integer', 'min:1'],
            'accent_color_id' => ['nullable', 'integer', 'min:1'],
            'insert_ids' => ['sometimes', 'array', 'max:20'],
            'insert_ids.*' => ['integer', 'min:1', 'distinct'],
            'font_id' => ['nullable', 'integer', 'min:1'],
            'text_color_id' => ['nullable', 'integer', 'min:1'],
            'personalization_text' => ['nullable', 'string', 'max:300'],
            'personalization_note' => ['nullable', 'string', 'max:300'],
            'note' => ['nullable', 'string', 'max:300'],
            'inspiration_asset_ids' => ['prohibited'],
        ];
    }
}
