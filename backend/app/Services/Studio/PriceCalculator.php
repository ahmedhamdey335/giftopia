<?php

namespace App\Services\Studio;

final class PriceCalculator
{
    /**
     * @return array{items: list<array{key: string, id: int, name: string, amount: int}>, total: int, currency: string}
     */
    public function calculate(StudioConfiguration $config): array
    {
        $items = [];
        $total = 0;

        $add = static function (string $key, ?object $option, string $nameField, string $priceField) use (&$items, &$total): void {
            if ($option === null) {
                return;
            }

            $amount = (int) $option->{$priceField};
            $items[] = [
                'key' => $key,
                'id' => (int) $option->getKey(),
                'name' => (string) $option->{$nameField},
                'amount' => $amount,
            ];
            $total += $amount;
        };

        $add('base_item', $config->baseItem, 'name', 'base_price');
        $add('mold', $config->mold, 'name', 'price_delta');
        $add('base_color', $config->baseColor, 'name', 'price_delta');
        $add('accent_color', $config->accentColor, 'name', 'price_delta');
        $add('technique', $config->technique, 'name', 'price_delta');

        foreach ($config->inserts as $insert) {
            $add('insert', $insert, 'name', 'price');
        }

        if ($config->hasPersonalization()) {
            $add('font', $config->font, 'name', 'price_delta');
            $add('text_color', $config->textColor, 'name', 'price_delta');
        }

        return [
            'items' => $items,
            'total' => $total,
            'currency' => (string) config('studio.currency', 'EGP'),
        ];
    }
}
