<?php

namespace App\Services;

use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\OptionRule;
use App\Models\Technique;
use App\Models\TextColor;
use Illuminate\Database\Eloquent\Model;

final class CatalogVersionService
{
    public function current(): string
    {
        $models = [
            BaseItem::class,
            Mold::class,
            Mood::class,
            ColorSwatch::class,
            Technique::class,
            Insert::class,
            Font::class,
            TextColor::class,
            OptionRule::class,
        ];
        $state = [];
        foreach ($models as $model) {
            /** @var class-string<Model> $model */
            $state[$model] = [
                'count' => $model::query()->count(),
                'updated_at' => $model::query()->max('updated_at'),
            ];
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
