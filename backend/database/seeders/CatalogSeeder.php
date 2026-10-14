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
        $coasters = BaseItem::query()->create([
            'name' => 'Round Coaster Set',
            'slug' => 'round-coaster-set',
            'base_price' => 28000,
            'piece_count' => 4,
            'labor_minutes' => 45,
            'cure_hours' => 24,
            'max_text_chars' => 18,
            'is_active' => true,
        ]);

        $bookmarks = BaseItem::query()->create([
            'name' => 'Resin Bookmark Pair',
            'slug' => 'resin-bookmark-pair',
            'base_price' => 16000,
            'piece_count' => 2,
            'labor_minutes' => 30,
            'cure_hours' => 18,
            'max_text_chars' => 24,
            'is_active' => true,
        ]);

        $tray = BaseItem::query()->create([
            'name' => 'Serving Tray',
            'slug' => 'serving-tray',
            'base_price' => 95000,
            'piece_count' => 1,
            'labor_minutes' => 90,
            'cure_hours' => 48,
            'max_text_chars' => 32,
            'is_active' => true,
        ]);

        $keychain = BaseItem::query()->create([
            'name' => 'Letter Keychain',
            'slug' => 'letter-keychain',
            'base_price' => 9000,
            'piece_count' => 1,
            'labor_minutes' => 20,
            'cure_hours' => 12,
            'max_text_chars' => 1,
            'is_active' => true,
        ]);

        $coasterStandard = Mold::query()->create([
            'base_item_id' => $coasters->id,
            'name' => 'Classic Round',
            'size_label' => '9 cm',
            'depth_mm' => 8.00,
            'price_delta' => 0,
        ]);

        $coasterDeep = Mold::query()->create([
            'base_item_id' => $coasters->id,
            'name' => 'Deep Round',
            'size_label' => '10 cm',
            'depth_mm' => 12.00,
            'price_delta' => 4000,
        ]);

        $bookmarkSlim = Mold::query()->create([
            'base_item_id' => $bookmarks->id,
            'name' => 'Slim Rectangle',
            'size_label' => '14 x 3 cm',
            'depth_mm' => 4.00,
            'price_delta' => 0,
        ]);

        Mold::query()->create([
            'base_item_id' => $bookmarks->id,
            'name' => 'Wide Rectangle',
            'size_label' => '14 x 4.5 cm',
            'depth_mm' => 5.50,
            'price_delta' => 2000,
        ]);

        Mold::query()->create([
            'base_item_id' => $tray->id,
            'name' => 'Oval Serving',
            'size_label' => '30 x 20 cm',
            'depth_mm' => 14.00,
            'price_delta' => 0,
        ]);

        $trayDeep = Mold::query()->create([
            'base_item_id' => $tray->id,
            'name' => 'Deep Rectangle',
            'size_label' => '32 x 22 cm',
            'depth_mm' => 18.00,
            'price_delta' => 12000,
        ]);

        Mold::query()->create([
            'base_item_id' => $keychain->id,
            'name' => 'Circle Charm',
            'size_label' => '4 cm',
            'depth_mm' => 6.00,
            'price_delta' => 0,
        ]);

        $pearl = ColorSwatch::query()->create([
            'name' => 'Pearl White',
            'hex' => '#F6F1EA',
            'role' => ColorSwatchRole::Base,
            'price_delta' => 0,
            'is_available' => true,
        ]);

        $ocean = ColorSwatch::query()->create([
            'name' => 'Ocean Teal',
            'hex' => '#1B6B6B',
            'role' => ColorSwatchRole::Base,
            'price_delta' => 500,
            'is_available' => true,
        ]);

        $blush = ColorSwatch::query()->create([
            'name' => 'Blush Pink',
            'hex' => '#E8A0BF',
            'role' => ColorSwatchRole::Base,
            'price_delta' => 500,
            'is_available' => true,
        ]);

        ColorSwatch::query()->create([
            'name' => 'Sage Green',
            'hex' => '#9CAF88',
            'role' => ColorSwatchRole::Base,
            'price_delta' => 500,
            'is_available' => true,
        ]);

        ColorSwatch::query()->create([
            'name' => 'Charcoal Smoke',
            'hex' => '#3A3A3A',
            'role' => ColorSwatchRole::Base,
            'price_delta' => 0,
            'is_available' => true,
        ]);

        $gold = ColorSwatch::query()->create([
            'name' => 'Metallic Gold',
            'hex' => '#C9A227',
            'role' => ColorSwatchRole::Accent,
            'price_delta' => 1500,
            'is_available' => true,
        ]);

        ColorSwatch::query()->create([
            'name' => 'Copper Leaf',
            'hex' => '#B87333',
            'role' => ColorSwatchRole::Accent,
            'price_delta' => 1500,
            'is_available' => true,
        ]);

        $silver = ColorSwatch::query()->create([
            'name' => 'Silver Mist',
            'hex' => '#C0C6CE',
            'role' => ColorSwatchRole::Accent,
            'price_delta' => 1000,
            'is_available' => true,
        ]);

        $solid = Technique::query()->create([
            'name' => 'Solid',
            'price_delta' => 0,
            'labor_minutes' => 0,
            'requires_accent' => false,
        ]);

        $gradient = Technique::query()->create([
            'name' => 'Gradient',
            'price_delta' => 2500,
            'labor_minutes' => 15,
            'requires_accent' => true,
        ]);

        $marble = Technique::query()->create([
            'name' => 'Marble',
            'price_delta' => 3500,
            'labor_minutes' => 20,
            'requires_accent' => true,
        ]);

        Technique::query()->create([
            'name' => 'Tint',
            'price_delta' => 1500,
            'labor_minutes' => 8,
            'requires_accent' => false,
        ]);

        $lavender = Insert::query()->create([
            'type' => InsertType::Flower,
            'name' => 'Dried Lavender',
            'color' => 'purple',
            'price' => 3500,
            'labor_minutes' => 12,
            'min_depth_mm' => 8.00,
            'is_available' => true,
        ]);

        $babyBreath = Insert::query()->create([
            'type' => InsertType::Flower,
            'name' => "Baby's Breath",
            'color' => 'white',
            'price' => 3000,
            'labor_minutes' => 10,
            'min_depth_mm' => 7.00,
            'is_available' => true,
        ]);

        $goldFoil = Insert::query()->create([
            'type' => InsertType::Foil,
            'name' => 'Gold Foil Flakes',
            'color' => 'gold',
            'price' => 2000,
            'labor_minutes' => 6,
            'min_depth_mm' => 4.00,
            'is_available' => true,
        ]);

        Insert::query()->create([
            'type' => InsertType::Shell,
            'name' => 'Crushed Shell Mix',
            'color' => 'iridescent',
            'price' => 2500,
            'labor_minutes' => 8,
            'min_depth_mm' => 6.00,
            'is_available' => true,
        ]);

        $glitter = Insert::query()->create([
            'type' => InsertType::Glitter,
            'name' => 'Fine Rose Gold Glitter',
            'color' => 'rose gold',
            'price' => 1500,
            'labor_minutes' => 5,
            'min_depth_mm' => 3.00,
            'is_available' => true,
        ]);

        $script = Font::query()->create([
            'name' => 'Script Flourish',
            'price_delta' => 1500,
        ]);

        Font::query()->create([
            'name' => 'Modern Sans',
            'price_delta' => 0,
        ]);

        Font::query()->create([
            'name' => 'Classic Serif',
            'price_delta' => 500,
        ]);

        TextColor::query()->create([
            'name' => 'Gold Leaf',
            'price_delta' => 1000,
        ]);

        TextColor::query()->create([
            'name' => 'Pearl White',
            'price_delta' => 0,
        ]);

        TextColor::query()->create([
            'name' => 'Ink Black',
            'price_delta' => 0,
        ]);

        TextColor::query()->create([
            'name' => 'Silver',
            'price_delta' => 800,
        ]);

        Mood::query()->create([
            'name' => 'Coastal Calm',
            'is_active' => true,
            'preset_json' => [
                'technique' => $gradient->name,
                'base_color' => $ocean->name,
                'accent_color' => $silver->name,
                'insert' => $goldFoil->name,
            ],
        ]);

        Mood::query()->create([
            'name' => 'Blush Garden',
            'is_active' => true,
            'preset_json' => [
                'technique' => $marble->name,
                'base_color' => $blush->name,
                'accent_color' => $gold->name,
                'insert' => $lavender->name,
            ],
        ]);

        Mood::query()->create([
            'name' => 'Quiet Pearl',
            'is_active' => true,
            'preset_json' => [
                'technique' => $solid->name,
                'base_color' => $pearl->name,
                'accent_color' => null,
                'insert' => $babyBreath->name,
            ],
        ]);

        OptionRule::query()->create([
            'subject_type' => 'insert',
            'subject_id' => $lavender->id,
            'rule' => OptionRuleAction::Blocks,
            'target_type' => 'mold',
            'target_id' => $bookmarkSlim->id,
            'message' => 'Dried lavender needs at least 8mm of resin depth.',
        ]);

        OptionRule::query()->create([
            'subject_type' => 'insert',
            'subject_id' => $babyBreath->id,
            'rule' => OptionRuleAction::Blocks,
            'target_type' => 'mold',
            'target_id' => $bookmarkSlim->id,
            'message' => "Baby's breath is too bulky for the slim bookmark mold.",
        ]);

        OptionRule::query()->create([
            'subject_type' => 'insert',
            'subject_id' => $lavender->id,
            'rule' => OptionRuleAction::Requires,
            'target_type' => 'mold',
            'target_id' => $coasterDeep->id,
            'message' => 'Use a deep mold so the flowers sit fully under the pour.',
        ]);

        OptionRule::query()->create([
            'subject_type' => 'technique',
            'subject_id' => $marble->id,
            'rule' => OptionRuleAction::Requires,
            'target_type' => 'color_swatch',
            'target_id' => $gold->id,
            'message' => 'Marble looks best with a metallic gold accent vein.',
        ]);

        OptionRule::query()->create([
            'subject_type' => 'insert',
            'subject_id' => $glitter->id,
            'rule' => OptionRuleAction::Blocks,
            'target_type' => 'technique',
            'target_id' => $marble->id,
            'message' => 'Fine glitter muddies marble veins; choose solid or gradient instead.',
        ]);

        OptionRule::query()->create([
            'subject_type' => 'font',
            'subject_id' => $script->id,
            'rule' => OptionRuleAction::Requires,
            'target_type' => 'base_item',
            'target_id' => $tray->id,
            'message' => 'Script lettering needs the larger tray surface to stay readable.',
        ]);

        OptionRule::query()->create([
            'subject_type' => 'insert',
            'subject_id' => $goldFoil->id,
            'rule' => OptionRuleAction::Requires,
            'target_type' => 'mold',
            'target_id' => $trayDeep->id,
            'message' => 'Heavy foil coverage is reserved for the deep tray pour.',
        ]);

        OptionRule::query()->create([
            'subject_type' => 'insert',
            'subject_id' => $lavender->id,
            'rule' => OptionRuleAction::Blocks,
            'target_type' => 'base_item',
            'target_id' => $keychain->id,
            'message' => 'Botanical inserts do not fit letter keychains.',
        ]);
    }
}
