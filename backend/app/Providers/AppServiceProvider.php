<?php

namespace App\Providers;

use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'base_item' => BaseItem::class,
            'mold' => Mold::class,
            'mood' => Mood::class,
            'color_swatch' => ColorSwatch::class,
            'technique' => Technique::class,
            'insert' => Insert::class,
            'font' => Font::class,
            'text_color' => TextColor::class,
            'user' => User::class,
        ]);
    }
}
