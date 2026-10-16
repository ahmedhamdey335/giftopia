<?php

namespace Database\Factories;

use App\Enums\ColorSwatchRole;
use App\Models\ColorSwatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorSwatch>
 */
class ColorSwatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->colorName(),
            'hex' => fake()->hexColor(),
            'role' => fake()->randomElement(ColorSwatchRole::cases()),
            'price_delta' => 0,
            'is_available' => true,
        ];
    }
}
