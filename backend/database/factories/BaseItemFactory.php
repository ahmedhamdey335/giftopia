<?php

namespace Database\Factories;

use App\Models\BaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseItem>
 */
class BaseItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'base_price' => fake()->numberBetween(5000, 100000),
            'piece_count' => fake()->numberBetween(1, 8),
            'labor_minutes' => fake()->numberBetween(15, 120),
            'cure_hours' => fake()->numberBetween(12, 72),
            'max_text_chars' => fake()->numberBetween(0, 40),
            'is_active' => true,
        ];
    }
}
