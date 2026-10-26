<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudioEventBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*.session_id' => ['required', 'uuid'],
            'events.*.step' => ['required', 'integer', 'between:1,6'],
            'events.*.event' => ['sometimes', 'string', 'in:viewed,completed,abandoned'],
            'events.*.created_at' => ['sometimes', 'date'],
        ];
    }
}
