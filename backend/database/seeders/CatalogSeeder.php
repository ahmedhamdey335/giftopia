<?php

namespace Database\Seeders;

use App\Enums\ColorSwatchRole;
use App\Enums\InsertType;
use App\Enums\OptionRuleAction;
use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\OptionRule;
use App\Models\Technique;
use App\Models\TextColor;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [];
        foreach ([
            ['Round Coaster Set', 'round-coaster-set', 28000, 4, 45, 24, 18],
            ['Resin Bookmark Pair', 'resin-bookmark-pair', 16000, 2, 30, 18, 24],
            ['Serving Tray', 'serving-tray', 95000, 1, 90, 48, 32],
            ['Letter Keychain', 'letter-keychain', 9000, 1, 20, 12, 1],
        ] as [$name, $slug, $price, $pieces, $labor, $cure, $maxText]) {
            $items[$slug] = BaseItem::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'base_price' => $price,
                    'piece_count' => $pieces,
                    'labor_minutes' => $labor,
                    'cure_hours' => $cure,
                    'max_text_chars' => $maxText,
                    'is_active' => true,
                ],
            );
        }

        $molds = [];
        foreach ([
            ['round-coaster-set', 'Classic Round', '9 cm', 8, 0],
            ['round-coaster-set', 'Deep Round', '10 cm', 12, 4000],
            ['resin-bookmark-pair', 'Slim Rectangle', '14 x 3 cm', 4, 0],
            ['resin-bookmark-pair', 'Wide Rectangle', '14 x 4.5 cm', 5.5, 2000],
            ['serving-tray', 'Oval Serving', '30 x 20 cm', 14, 0],
            ['serving-tray', 'Deep Rectangle', '32 x 22 cm', 18, 12000],
            ['letter-keychain', 'Circle Charm', '4 cm', 6, 0],
        ] as [$itemSlug, $name, $size, $depth, $price]) {
            $molds[$name] = Mold::query()->updateOrCreate(
                ['base_item_id' => $items[$itemSlug]->id, 'name' => $name],
                ['size_label' => $size, 'depth_mm' => $depth, 'price_delta' => $price],
            );
        }

        $swatches = [];
        foreach ([
            ['Pearl White', '#F6F1EA', ColorSwatchRole::Base, 0],
            ['Ocean Teal', '#1B6B6B', ColorSwatchRole::Base, 500],
            ['Blush Pink', '#E8A0BF', ColorSwatchRole::Base, 500],
            ['Sage Green', '#9CAF88', ColorSwatchRole::Base, 500],
            ['Charcoal Smoke', '#3A3A3A', ColorSwatchRole::Base, 0],
            ['Metallic Gold', '#C9A227', ColorSwatchRole::Accent, 1500],
            ['Copper Leaf', '#B87333', ColorSwatchRole::Accent, 1500],
            ['Silver Mist', '#C0C6CE', ColorSwatchRole::Accent, 1000],
        ] as [$name, $hex, $role, $price]) {
            $swatches[$name] = ColorSwatch::query()->updateOrCreate(
                ['name' => $name, 'role' => $role],
                ['hex' => $hex, 'price_delta' => $price, 'is_available' => true],
            );
        }

        $techniques = [];
        foreach ([
            ['Solid', 0, 0, false],
            ['Gradient', 2500, 15, true],
            ['Marble', 3500, 20, true],
            ['Tint', 1500, 8, false],
        ] as [$name, $price, $labor, $requiresAccent]) {
            $techniques[$name] = Technique::query()->updateOrCreate(
                ['name' => $name],
                ['price_delta' => $price, 'labor_minutes' => $labor, 'requires_accent' => $requiresAccent],
            );
        }

        $inserts = [];
        foreach ([
            ['Dried Lavender', InsertType::Flower, 'purple', 3500, 12, 8],
            ["Baby's Breath", InsertType::Flower, 'white', 3000, 10, 7],
            ['Gold Foil Flakes', InsertType::Foil, 'gold', 2000, 6, 4],
            ['Crushed Shell Mix', InsertType::Shell, 'iridescent', 2500, 8, 6],
            ['Fine Rose Gold Glitter', InsertType::Glitter, 'rose gold', 1500, 5, 3],
        ] as [$name, $type, $color, $price, $labor, $minDepth]) {
            $inserts[$name] = Insert::query()->updateOrCreate(
                ['name' => $name],
                [
                    'type' => $type,
                    'color' => $color,
                    'price' => $price,
                    'labor_minutes' => $labor,
                    'min_depth_mm' => $minDepth,
                    'is_available' => true,
                ],
            );
        }

        $fonts = [];
        foreach ([['Script Flourish', 1500], ['Modern Sans', 0], ['Classic Serif', 500]] as [$name, $price]) {
            $fonts[$name] = Font::query()->updateOrCreate(['name' => $name], ['price_delta' => $price]);
        }

        $textColors = [];
        foreach ([['Gold Leaf', 1000], ['Pearl White', 0], ['Ink Black', 0], ['Silver', 800]] as [$name, $price]) {
            $textColors[$name] = TextColor::query()->updateOrCreate(['name' => $name], ['price_delta' => $price]);
        }

        $legacyMoodNames = [
            'Coastal Calm' => 'Summer/Cheerful',
            'Blush Garden' => 'Romantic Pastel',
            'Quiet Pearl' => 'Minimalist/Clean',
        ];
        foreach ($legacyMoodNames as $old => $new) {
            $legacy = Mood::query()->where('name', $old)->first();
            if (! $legacy) {
                continue;
            }

            $canonical = Mood::query()->where('name', $new)->where('id', '!=', $legacy->id)->exists();
            $legacy->update($canonical
                ? ['is_active' => false]
                : ['name' => $new, 'is_active' => true]);
        }

        $moods = [];
        foreach ([
            ['Summer/Cheerful', 'Gradient', 'Ocean Teal', 'Silver Mist', 'Gold Foil Flakes'],
            ['Moody/Deep', 'Marble', 'Charcoal Smoke', 'Copper Leaf', 'Crushed Shell Mix'],
            ['Romantic Pastel', 'Marble', 'Blush Pink', 'Metallic Gold', 'Dried Lavender'],
            ['Vintage/Ancient', 'Tint', 'Sage Green', 'Copper Leaf', 'Gold Foil Flakes'],
            ['Minimalist/Clean', 'Solid', 'Pearl White', null, "Baby's Breath"],
        ] as [$name, $technique, $base, $accent, $insert]) {
            $moods[$name] = Mood::query()->updateOrCreate(
                ['name' => $name],
                [
                    'is_active' => true,
                    'preset_json' => [
                        'technique' => $technique,
                        'base_color' => $base,
                        'accent_color' => $accent,
                        'insert' => $insert,
                    ],
                ],
            );
        }

        $rules = [
            ['insert', $inserts['Dried Lavender']->id, OptionRuleAction::Blocks, 'mold', $molds['Slim Rectangle']->id, 'Dried lavender needs at least 8mm of resin depth.'],
            ['insert', $inserts["Baby's Breath"]->id, OptionRuleAction::Blocks, 'mold', $molds['Slim Rectangle']->id, "Baby's breath is too bulky for the slim bookmark mold."],
            ['insert', $inserts['Dried Lavender']->id, OptionRuleAction::Requires, 'mold', $molds['Deep Round']->id, 'Use a deep mold so the flowers sit fully under the pour.'],
            ['technique', $techniques['Marble']->id, OptionRuleAction::Requires, 'color_swatch', $swatches['Metallic Gold']->id, 'Marble looks best with a metallic gold accent vein.'],
            ['insert', $inserts['Fine Rose Gold Glitter']->id, OptionRuleAction::Blocks, 'technique', $techniques['Marble']->id, 'Fine glitter muddies marble veins; choose solid or gradient instead.'],
            ['font', $fonts['Script Flourish']->id, OptionRuleAction::Requires, 'base_item', $items['serving-tray']->id, 'Script lettering needs the larger tray surface to stay readable.'],
            ['insert', $inserts['Gold Foil Flakes']->id, OptionRuleAction::Requires, 'mold', $molds['Deep Rectangle']->id, 'Heavy foil coverage is reserved for the deep tray pour.'],
            ['insert', $inserts['Dried Lavender']->id, OptionRuleAction::Blocks, 'base_item', $items['letter-keychain']->id, 'Botanical inserts do not fit letter keychains.'],
        ];

        foreach ($rules as [$subjectType, $subjectId, $rule, $targetType, $targetId, $message]) {
            OptionRule::query()->updateOrCreate(
                [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'rule' => $rule,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                ],
                ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'rule' => $rule,
                    'target_type' => $targetType, 'target_id' => $targetId, 'message' => $message],
            );
        }
    }
}
