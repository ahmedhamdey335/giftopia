<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlackoutDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('blackout_dates', 'date')->ignore($this->route('blackoutDate')),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
