<?php

namespace App\Services\Studio;

use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Technique;
use App\Models\TextColor;
use Illuminate\Support\Collection;

final class StudioConfiguration
{
    /**
     * @param  Collection<int, Insert>  $inserts
     */
    public function __construct(
        public readonly ?BaseItem $baseItem,
        public readonly ?Mold $mold,
        public readonly ?Technique $technique,
        public readonly ?ColorSwatch $baseColor,
        public readonly ?ColorSwatch $accentColor,
        public readonly Collection $inserts,
        public readonly ?Font $font,
        public readonly ?TextColor $textColor,
        public readonly ?string $personalizationText,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $insertIds = collect($input['insert_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $text = isset($input['personalization_text'])
            ? trim((string) $input['personalization_text'])
            : null;

        return new self(
            baseItem: isset($input['base_item_id']) ? BaseItem::query()->find($input['base_item_id']) : null,
            mold: isset($input['mold_id']) ? Mold::query()->find($input['mold_id']) : null,
            technique: isset($input['technique_id']) ? Technique::query()->find($input['technique_id']) : null,
            baseColor: isset($input['base_color_id']) ? ColorSwatch::query()->find($input['base_color_id']) : null,
            accentColor: isset($input['accent_color_id']) ? ColorSwatch::query()->find($input['accent_color_id']) : null,
            inserts: $insertIds->isEmpty()
                ? collect()
                : Insert::query()->whereIn('id', $insertIds)->get(),
            font: isset($input['font_id']) ? Font::query()->find($input['font_id']) : null,
            textColor: isset($input['text_color_id']) ? TextColor::query()->find($input['text_color_id']) : null,
            personalizationText: $text === '' ? null : $text,
        );
    }

    /**
     * @return list<array{type: string, id: int}>
     */
    public function selectedOptions(): array
    {
        $options = [];

        if ($this->baseItem) {
            $options[] = ['type' => 'base_item', 'id' => $this->baseItem->id];
        }

        if ($this->mold) {
            $options[] = ['type' => 'mold', 'id' => $this->mold->id];
        }

        if ($this->technique) {
            $options[] = ['type' => 'technique', 'id' => $this->technique->id];
        }

        if ($this->baseColor) {
            $options[] = ['type' => 'color_swatch', 'id' => $this->baseColor->id];
        }

        if ($this->accentColor) {
            $options[] = ['type' => 'color_swatch', 'id' => $this->accentColor->id];
        }

        foreach ($this->inserts as $insert) {
            $options[] = ['type' => 'insert', 'id' => $insert->id];
        }

        if ($this->font) {
            $options[] = ['type' => 'font', 'id' => $this->font->id];
        }

        if ($this->textColor) {
            $options[] = ['type' => 'text_color', 'id' => $this->textColor->id];
        }

        return $options;
    }

    public function hasPersonalization(): bool
    {
        return $this->personalizationText !== null && $this->personalizationText !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'base_item' => $this->baseItem ? [
                'id' => $this->baseItem->id,
                'name' => $this->baseItem->name,
                'slug' => $this->baseItem->slug,
            ] : null,
            'mold' => $this->mold ? [
                'id' => $this->mold->id,
                'name' => $this->mold->name,
                'size_label' => $this->mold->size_label,
                'depth_mm' => $this->mold->depth_mm,
            ] : null,
            'technique' => $this->technique ? [
                'id' => $this->technique->id,
                'name' => $this->technique->name,
            ] : null,
            'base_color' => $this->baseColor ? [
                'id' => $this->baseColor->id,
                'name' => $this->baseColor->name,
                'hex' => $this->baseColor->hex,
            ] : null,
            'accent_color' => $this->accentColor ? [
                'id' => $this->accentColor->id,
                'name' => $this->accentColor->name,
                'hex' => $this->accentColor->hex,
            ] : null,
            'inserts' => $this->inserts->map(fn (Insert $insert) => [
                'id' => $insert->id,
                'name' => $insert->name,
                'type' => $insert->type->value,
            ])->values()->all(),
            'font' => $this->font ? [
                'id' => $this->font->id,
                'name' => $this->font->name,
            ] : null,
            'text_color' => $this->textColor ? [
                'id' => $this->textColor->id,
                'name' => $this->textColor->name,
            ] : null,
            'personalization_text' => $this->personalizationText,
        ];
    }

    public function totalLaborMinutes(): int
    {
        $minutes = (int) ($this->baseItem?->labor_minutes ?? 0);
        $minutes += (int) ($this->technique?->labor_minutes ?? 0);
        $minutes += (int) $this->inserts->sum('labor_minutes');

        return $minutes;
    }

    public function cureHours(): int
    {
        return (int) ($this->baseItem?->cure_hours ?? 0);
    }
}
