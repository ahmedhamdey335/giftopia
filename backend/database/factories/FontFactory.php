<?php

namespace Database\Factories;

use App\Models\Font;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Font>
 */
class FontFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'price_delta' => 0,
        ];
    }
}
