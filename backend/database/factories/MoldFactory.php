<?php

namespace Database\Factories;

use App\Models\BaseItem;
use App\Models\Mold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mold>
 */
class MoldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'base_item_id' => BaseItem::factory(),
            'name' => fake()->unique()->words(2, true),
            'size_label' => '9 cm',
            'depth_mm' => 10,
            'price_delta' => 0,
        ];
    }
}
