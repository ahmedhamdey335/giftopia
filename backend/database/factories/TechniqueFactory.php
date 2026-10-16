<?php

namespace Database\Factories;

use App\Models\Technique;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technique>
 */
class TechniqueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'price_delta' => 0,
            'labor_minutes' => 0,
            'requires_accent' => false,
        ];
    }
}
