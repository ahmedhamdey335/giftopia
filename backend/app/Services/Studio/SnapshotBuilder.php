<?php

namespace App\Services\Studio;

use App\Models\Insert;

final class SnapshotBuilder
{
    /**
     * @param  array{items: list<array<string, mixed>>, total: int, currency: string}  $price
     * @return array<string, mixed>
     */
    public function build(StudioConfiguration $config, array $price): array
    {
        return [
            'schema_version' => 1,
            'base_item' => $config->baseItem ? [
                'id' => $config->baseItem->id,
                'name' => $config->baseItem->name,
                'piece_count' => $config->baseItem->piece_count,
                'price' => $config->baseItem->base_price,
                'mold' => $config->mold ? [
                    'id' => $config->mold->id,
                    'name' => $config->mold->name,
                    'size_label' => $config->mold->size_label,
                    'depth_mm' => $config->mold->depth_mm,
                    'price' => $config->mold->price_delta,
                ] : null,
            ] : null,
            'mood' => $config->mood ? [
                'id' => $config->mood->id,
                'name' => $config->mood->name,
            ] : null,
            'color' => [
                'base' => $config->baseColor ? [
                    'id' => $config->baseColor->id,
                    'name' => $config->baseColor->name,
                    'hex' => $config->baseColor->hex,
                    'price' => $config->baseColor->price_delta,
                ] : null,
                'accent' => $config->accentColor ? [
                    'id' => $config->accentColor->id,
                    'name' => $config->accentColor->name,
                    'hex' => $config->accentColor->hex,
                    'price' => $config->accentColor->price_delta,
                ] : null,
                'technique' => $config->technique ? [
                    'id' => $config->technique->id,
                    'name' => $config->technique->name,
                    'price' => $config->technique->price_delta,
                    'labor_minutes' => $config->technique->labor_minutes,
                ] : null,
            ],
            'inserts' => $config->inserts->map(fn (Insert $insert) => [
                'id' => $insert->id,
                'type' => $insert->type->value,
                'name' => $insert->name,
                'color' => $insert->color,
                'price' => $insert->price,
                'labor_minutes' => $insert->labor_minutes,
            ])->values()->all(),
            'personalization' => [
                'text' => $config->personalizationText,
                'font' => $config->font ? [
                    'id' => $config->font->id,
                    'name' => $config->font->name,
                    'price' => $config->font->price_delta,
                ] : null,
                'text_color' => $config->textColor ? [
                    'id' => $config->textColor->id,
                    'name' => $config->textColor->name,
                    'price' => $config->textColor->price_delta,
                ] : null,
                'note' => $config->personalizationNote,
            ],
            'price' => [
                'breakdown' => $price['items'],
                'total' => (int) $price['total'],
                'currency' => $price['currency'],
            ],
            'labor_minutes' => $config->totalLaborMinutes(),
            'cure_hours' => (int) ($config->baseItem?->cure_hours ?? 0),
        ];
    }
}
