<?php

namespace App\Support;

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
use Illuminate\Http\Request;

final class CatalogRegistry
{
    /**
     * @var array<string, array{model: class-string<Model>, availability: ?string, rules: array<string, list<string>>}>
     */
    private const DEFINITIONS = [
        'base-items' => [
            'model' => BaseItem::class,
            'availability' => 'is_active',
            'rules' => [
                'name' => ['string', 'max:255'],
                'slug' => ['string', 'max:255'],
                'base_price' => ['integer', 'min:0'],
                'piece_count' => ['integer', 'min:1'],
                'labor_minutes' => ['integer', 'min:0'],
                'cure_hours' => ['integer', 'min:0'],
                'max_text_chars' => ['integer', 'min:0'],
                'is_active' => ['boolean'],
            ],
        ],
        'molds' => [
            'model' => Mold::class,
            'availability' => null,
            'rules' => [
                'base_item_id' => ['integer', 'exists:base_items,id'],
                'name' => ['string', 'max:255'],
                'size_label' => ['string', 'max:255'],
                'depth_mm' => ['numeric', 'min:0'],
                'price_delta' => ['integer', 'min:0'],
            ],
        ],
        'moods' => [
            'model' => Mood::class,
            'availability' => 'is_active',
            'rules' => [
                'name' => ['string', 'max:255'],
                'preset_json' => ['array'],
                'is_active' => ['boolean'],
            ],
        ],
        'color-swatches' => [
            'model' => ColorSwatch::class,
            'availability' => 'is_available',
            'rules' => [
                'name' => ['string', 'max:255'],
                'hex' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'role' => ['in:base,accent'],
                'price_delta' => ['integer', 'min:0'],
                'is_available' => ['boolean'],
            ],
        ],
        'techniques' => [
            'model' => Technique::class,
            'availability' => null,
            'rules' => [
                'name' => ['string', 'max:255'],
                'price_delta' => ['integer', 'min:0'],
                'labor_minutes' => ['integer', 'min:0'],
                'requires_accent' => ['boolean'],
            ],
        ],
        'inserts' => [
            'model' => Insert::class,
            'availability' => 'is_available',
            'rules' => [
                'type' => ['in:flower,foil,shell,glitter'],
                'name' => ['string', 'max:255'],
                'color' => ['nullable', 'string', 'max:255'],
                'price' => ['integer', 'min:0'],
                'labor_minutes' => ['integer', 'min:0'],
                'min_depth_mm' => ['numeric', 'min:0'],
                'is_available' => ['boolean'],
            ],
        ],
        'fonts' => [
            'model' => Font::class,
            'availability' => null,
            'rules' => [
                'name' => ['string', 'max:255'],
                'price_delta' => ['integer', 'min:0'],
            ],
        ],
        'text-colors' => [
            'model' => TextColor::class,
            'availability' => null,
            'rules' => [
                'name' => ['string', 'max:255'],
                'price_delta' => ['integer', 'min:0'],
            ],
        ],
        'option-rules' => [
            'model' => OptionRule::class,
            'availability' => null,
            'rules' => [
                'subject_type' => ['string'],
                'subject_id' => ['integer', 'min:1'],
                'rule' => ['in:blocks,requires'],
                'target_type' => ['string'],
                'target_id' => ['integer', 'min:1'],
                'message' => ['string', 'max:255'],
            ],
        ],
    ];

    /**
     * @return array{model: class-string<Model>, availability: ?string, rules: array<string, list<string>>}
     */
    public static function forRequest(Request $request): array
    {
        $name = (string) $request->route()?->getName();
        $prefix = 'admin.catalog.';
        abort_unless(str_starts_with($name, $prefix), 404);

        $slug = explode('.', substr($name, strlen($prefix)), 2)[0];
        abort_unless(isset(self::DEFINITIONS[$slug]), 404);

        return self::DEFINITIONS[$slug];
    }

    /**
     * @return array<string, array{model: class-string<Model>, availability: ?string, rules: array<string, list<string>>}>
     */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return array<string, class-string<Model>>
     */
    public static function morphTypes(): array
    {
        return [
            'base_item' => BaseItem::class,
            'mold' => Mold::class,
            'mood' => Mood::class,
            'color_swatch' => ColorSwatch::class,
            'technique' => Technique::class,
            'insert' => Insert::class,
            'font' => Font::class,
            'text_color' => TextColor::class,
        ];
    }
}
