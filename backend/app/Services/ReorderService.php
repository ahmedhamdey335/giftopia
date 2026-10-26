<?php

namespace App\Services;

use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Order;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use App\Services\Studio\StudioConfiguration;
use App\Services\Studio\StudioQuoteService;
use Illuminate\Support\Arr;

final class ReorderService
{
    public function __construct(
        private readonly CompatibilityValidator $validator,
        private readonly StudioQuoteService $quotes,
        private readonly CatalogVersionService $versions,
    ) {}

    /**
     * @return array{allowed: bool, quote: ?array, draft: array<string, mixed>, issues: array}
     */
    public function create(Order $order, User $user): array
    {
        $snapshot = $order->items()->firstOrFail()->customization;
        $configuration = [
            'base_item_id' => Arr::get($snapshot, 'base_item.id'),
            'mold_id' => Arr::get($snapshot, 'base_item.mold.id'),
            'mood_id' => Arr::get($snapshot, 'mood.id'),
            'technique_id' => Arr::get($snapshot, 'color.technique.id'),
            'base_color_id' => Arr::get($snapshot, 'color.base.id'),
            'accent_color_id' => Arr::get($snapshot, 'color.accent.id'),
            'insert_ids' => collect($snapshot['inserts'] ?? [])->pluck('id')->filter()->values()->all(),
            'font_id' => Arr::get($snapshot, 'personalization.font.id'),
            'text_color_id' => Arr::get($snapshot, 'personalization.text_color.id'),
            'personalization_text' => Arr::get($snapshot, 'personalization.text'),
            'personalization_note' => Arr::get($snapshot, 'personalization.note'),
            'inspiration_asset_ids' => [],
        ];
        $studio = StudioConfiguration::fromArray($configuration);
        $result = $this->validator->validate($studio, strict: true);
        $issues = $result->violations;
        foreach ($studio->unknown as $unknown) {
            $issues[] = [
                'code' => 'option_unavailable',
                'field' => $unknown['field'],
                'id' => $unknown['id'],
                'name' => $this->optionName($unknown['field'], $unknown['id']),
            ];
        }
        $draft = [
            'schema_version' => 1,
            'option_ids' => Arr::only($configuration, [
                'base_item_id', 'mold_id', 'mood_id', 'technique_id', 'base_color_id',
                'accent_color_id', 'insert_ids', 'font_id', 'text_color_id',
            ]),
            'text' => $configuration['personalization_text'],
            'note' => $configuration['personalization_note'],
            'inspiration_asset_ids' => [],
            'saved_at' => now()->toIso8601String(),
            'catalog_version' => $this->versions->current(),
        ];
        if (! $result->allowed || $studio->unknown !== []) {
            return ['allowed' => false, 'quote' => null, 'draft' => $draft, 'issues' => $issues];
        }

        $quote = $this->quotes->create($studio, $user);

        return [
            'allowed' => true,
            'quote' => $this->quotes->response($quote),
            'draft' => $draft,
            'issues' => [],
        ];
    }

    private function optionName(string $field, mixed $id): ?string
    {
        $map = [
            'base_item_id' => BaseItem::class,
            'mold_id' => Mold::class,
            'mood_id' => Mood::class,
            'technique_id' => Technique::class,
            'base_color_id' => ColorSwatch::class,
            'accent_color_id' => ColorSwatch::class,
            'font_id' => Font::class,
            'text_color_id' => TextColor::class,
            'insert_ids' => Insert::class,
        ];
        $model = $map[$field] ?? null;

        return $model === null ? null : $model::query()->whereKey($id)->value('name');
    }
}
