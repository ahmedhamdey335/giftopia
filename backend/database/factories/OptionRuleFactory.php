<?php

namespace Database\Factories;

use App\Enums\OptionRuleAction;
use App\Models\BaseItem;
use App\Models\Mold;
use App\Models\OptionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionRule>
 */
class OptionRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_type' => 'base_item',
            'subject_id' => BaseItem::factory(),
            'rule' => fake()->randomElement(OptionRuleAction::cases()),
            'target_type' => 'mold',
            'target_id' => Mold::factory(),
            'message' => fake()->sentence(),
        ];
    }
}
