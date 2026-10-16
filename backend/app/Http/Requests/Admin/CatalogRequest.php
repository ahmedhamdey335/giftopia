<?php

namespace App\Http\Requests\Admin;

use App\Support\CatalogRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $definition = CatalogRegistry::forRequest($this);
        $creating = $this->isMethod('POST');
        $rules = [];

        foreach ($definition['rules'] as $field => $fieldRules) {
            $presenceRule = in_array('nullable', $fieldRules, true)
                ? []
                : [$creating ? 'required' : 'sometimes'];
            $rules[$field] = array_merge($presenceRule, $fieldRules);
        }

        if (isset($rules['subject_type'])) {
            $aliases = array_keys(CatalogRegistry::morphTypes());
            $rules['subject_type'] = array_merge($rules['subject_type'], [Rule::in($aliases)]);
            $rules['target_type'] = array_merge($rules['target_type'], [Rule::in($aliases)]);
        }

        if (isset($rules['slug'])) {
            $rules['slug'][] = Rule::unique('base_items', 'slug')->ignore($this->route('id'));
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('subject_type') && ! $this->has('target_type')) {
                return;
            }

            foreach (['subject' => 'subject_id', 'target' => 'target_id'] as $prefix => $idField) {
                $typeField = $prefix.'_type';
                $type = $this->input($typeField);
                $id = $this->input($idField);

                if (! isset($type, $id) || ! isset(CatalogRegistry::morphTypes()[$type])) {
                    continue;
                }

                $model = CatalogRegistry::morphTypes()[$type];
                if (! $model::query()->whereKey($id)->exists()) {
                    $validator->errors()->add($idField, 'The selected '.$prefix.' option does not exist.');
                }
            }
        });
    }
}
