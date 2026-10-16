<?php

namespace Database\Factories;

use App\Models\TextColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TextColor>
 */
class TextColorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->colorName(),
            'price_delta' => 0,
        ];
    }
}
