<?php

namespace Database\Factories;

use App\Enums\InsertType;
use App\Models\Insert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insert>
 */
class InsertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(InsertType::cases()),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->safeColorName(),
            'price' => 0,
            'labor_minutes' => 0,
            'min_depth_mm' => 0,
            'is_available' => true,
        ];
    }
}
