<?php

namespace Database\Factories;

use App\Models\BaseItem;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'base_item_id' => BaseItem::factory(),
            'configuration' => [],
            'config_hash' => hash('sha256', '{}'),
            'price_breakdown' => [],
            'total_price' => 10000,
            'ready_at' => now()->addDays(3),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
