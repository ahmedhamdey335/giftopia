<?php

namespace App\Services;

use App\Models\OptionRule;
use App\Models\Quote;
use App\Support\CatalogRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CatalogReferenceChecker
{
    public function isReferenced(Model $model): bool
    {
        $type = array_search($model::class, CatalogRegistry::morphTypes(), true);
        if ($type !== false && OptionRule::query()
            ->where(fn ($query) => $query->where('subject_type', $type)->where('subject_id', $model->getKey()))
            ->orWhere(fn ($query) => $query->where('target_type', $type)->where('target_id', $model->getKey()))
            ->exists()) {
            return true;
        }

        $configurationKeys = [
            'base_item' => ['base_item_id', 'base_item'],
            'mold' => ['mold_id', 'mold'],
            'mood' => ['mood_id', 'mood'],
            'color_swatch' => ['base_color_id', 'accent_color_id', 'base_color', 'accent_color', 'base', 'accent'],
            'technique' => ['technique_id', 'technique'],
            'insert' => ['insert_ids', 'inserts'],
            'font' => ['font_id', 'font'],
            'text_color' => ['text_color_id', 'text_color'],
        ];

        if ($type !== false) {
            $keys = $configurationKeys[$type];
            foreach (Quote::query()->select('configuration')->cursor() as $quote) {
                if ($this->containsReference($quote->configuration, $keys, $model->getKey())) {
                    return true;
                }
            }

            if (Schema::hasTable('order_items')) {
                foreach (\DB::table('order_items')->select('customization')->cursor() as $item) {
                    $snapshot = json_decode($item->customization, true);
                    if ($this->containsReference($snapshot, $keys, $model->getKey())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     */
    private function containsReference(mixed $value, array $keys, int|string $id, ?string $parentKey = null): bool
    {
        if (in_array($parentKey, $keys, true)) {
            if ($parentKey === 'insert_ids') {
                return is_array($value) && in_array($id, $value);
            }

            if ($parentKey === 'inserts' && is_array($value)) {
                foreach ($value as $insert) {
                    if (is_array($insert) && (string) ($insert['id'] ?? '') === (string) $id) {
                        return true;
                    }
                }

                return false;
            }

            if (is_array($value) && array_key_exists('id', $value)) {
                return (string) $value['id'] === (string) $id;
            }

            return ! is_array($value) && (string) $value === (string) $id;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $child) {
            if ($this->containsReference($child, $keys, $id, (string) $key)) {
                return true;
            }
        }

        return false;
    }
}
