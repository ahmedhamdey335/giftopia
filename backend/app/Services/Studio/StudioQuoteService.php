<?php

namespace App\Services\Studio;

use App\Models\Quote;
use App\Models\User;

final class StudioQuoteService
{
    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly LeadTimeEstimator $leadTime,
    ) {}

    public function create(StudioConfiguration $configuration, ?User $user): Quote
    {
        $normalized = $configuration->normalizedConfiguration();
        $hash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
        $price = $this->prices->calculate($configuration);

        return Quote::query()->create([
            'user_id' => $user?->id,
            'base_item_id' => $configuration->baseItem?->id,
            'configuration' => $normalized,
            'config_hash' => $hash,
            'price_breakdown' => $price,
            'total_price' => $price['total'],
            'ready_at' => $this->leadTime->estimate($configuration),
            'expires_at' => now()->addMinutes((int) config('studio.quote_ttl_minutes', 30)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function response(Quote $quote): array
    {
        return [
            'quote_id' => $quote->id,
            'price_breakdown' => $quote->price_breakdown,
            'total' => (int) $quote->total_price,
            'ready_at' => $quote->ready_at->toIso8601String(),
            'expires_at' => $quote->expires_at->toIso8601String(),
        ];
    }
}
