<?php

namespace App\Http\Requests\Admin;

use App\Support\CatalogRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CatalogAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $field = CatalogRegistry::forRequest($this)['availability'];
        $rules = [
            'is_active' => ['prohibited'],
            'is_available' => ['prohibited'],
        ];

        if ($field !== null) {
            $rules[$field] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $field = CatalogRegistry::forRequest($this)['availability'];

        $validator->after(function (Validator $validator) use ($field): void {
            foreach (array_keys($this->all()) as $input) {
                if ($input !== $field) {
                    $validator->errors()->add($input, 'Only the availability field may be changed.');
                }
            }
        });
    }
}
