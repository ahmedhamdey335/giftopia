<?php

namespace App\Services\Studio;

use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Technique;
use App\Models\TextColor;
use Illuminate\Support\Collection;

final class StudioConfiguration
{
    /**
     * @param  Collection<int, Insert>  $inserts
     * @param  list<array{field: string, id: mixed}>  $unknown
     */
    public function __construct(
        public readonly ?BaseItem $baseItem,
        public readonly ?Mold $mold,
        public readonly ?Mood $mood,
        public readonly ?Technique $technique,
        public readonly ?ColorSwatch $baseColor,
        public readonly ?ColorSwatch $accentColor,
        public readonly Collection $inserts,
        public readonly ?Font $font,
        public readonly ?TextColor $textColor,
        public readonly ?string $personalizationText,
        public readonly ?string $personalizationNote,
        public readonly array $unknown = [],
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
        $note = isset($input['personalization_note']) || isset($input['note'])
            ? trim((string) ($input['personalization_note'] ?? $input['note']))
            : null;

        $unknown = [];
        $baseItem = self::find(BaseItem::class, $input, 'base_item_id', $unknown);
        $mold = self::find(Mold::class, $input, 'mold_id', $unknown);
        $mood = self::find(Mood::class, $input, 'mood_id', $unknown);
        $technique = self::find(Technique::class, $input, 'technique_id', $unknown);
        $baseColor = self::find(ColorSwatch::class, $input, 'base_color_id', $unknown);
        $accentColor = self::find(ColorSwatch::class, $input, 'accent_color_id', $unknown);
        $font = self::find(Font::class, $input, 'font_id', $unknown);
        $textColor = self::find(TextColor::class, $input, 'text_color_id', $unknown);

        $inserts = $insertIds->isEmpty()
            ? collect()
            : Insert::query()->whereIn('id', $insertIds)->get();
        foreach ($insertIds->diff($inserts->pluck('id')) as $id) {
            $unknown[] = ['field' => 'insert_ids', 'id' => $id];
        }

        return new self(
            baseItem: $baseItem,
            mold: $mold,
            mood: $mood,
            technique: $technique,
            baseColor: $baseColor,
            accentColor: $accentColor,
            inserts: $inserts,
            font: $font,
            textColor: $textColor,
            personalizationText: $text === '' ? null : $text,
            personalizationNote: $note === '' ? null : $note,
            unknown: $unknown,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<array{field: string, id: mixed}>  $unknown
     */
    private static function find(string $model, array $input, string $field, array &$unknown): ?object
    {
        if (! isset($input[$field]) || $input[$field] === '') {
            return null;
        }

        $option = $model::query()->find($input[$field]);
        if ($option === null) {
            $unknown[] = ['field' => $field, 'id' => $input[$field]];
        }

        return $option;
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

        if ($this->mood) {
            $options[] = ['type' => 'mood', 'id' => $this->mood->id];
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

    public function totalLaborMinutes(): int
    {
        $minutes = (int) ($this->baseItem?->labor_minutes ?? 0);
        $minutes += (int) ($this->technique?->labor_minutes ?? 0);
        $minutes += (int) $this->inserts->sum('labor_minutes');

        return $minutes;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedConfiguration(): array
    {
        return [
            'base_item_id' => $this->baseItem?->id,
            'mold_id' => $this->mold?->id,
            'mood_id' => $this->mood?->id,
            'technique_id' => $this->technique?->id,
            'base_color_id' => $this->baseColor?->id,
            'accent_color_id' => $this->accentColor?->id,
            'insert_ids' => $this->inserts->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'font_id' => $this->font?->id,
            'text_color_id' => $this->textColor?->id,
            'personalization_text' => $this->personalizationText,
            'personalization_note' => $this->personalizationNote,
        ];
    }

    public function cureHours(): int
    {
        return (int) ($this->baseItem?->cure_hours ?? 0);
    }
}
