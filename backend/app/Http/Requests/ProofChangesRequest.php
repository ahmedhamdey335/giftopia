<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProofChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:1', 'max:2000']];
    }
}
