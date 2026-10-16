<?php

namespace Database\Factories;

use App\Models\Mood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mood>
 */
class MoodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'preset_json' => [],
            'is_active' => true,
        ];
    }
}
