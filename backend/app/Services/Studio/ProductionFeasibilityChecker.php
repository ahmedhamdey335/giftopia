<?php

namespace App\Services\Studio;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

final class ProductionFeasibilityChecker
{
    public function __construct(private readonly WorkingDayLeadTimeEstimator $estimator) {}

    public function isAtRisk(OrderItem $item): bool
    {
        if ($item->production_status === ProductionStatus::Packed
            || in_array($item->order->status, [
                OrderStatus::ReadyForDelivery,
                OrderStatus::OutForDelivery,
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
            ], true)) {
            return false;
        }

        $snapshot = $item->customization;
        $configuration = StudioConfiguration::fromArray([
            'base_item_id' => $snapshot['base_item']['id'] ?? null,
            'mold_id' => $snapshot['base_item']['mold']['id'] ?? null,
            'mood_id' => $snapshot['mood']['id'] ?? null,
            'technique_id' => $snapshot['color']['technique']['id'] ?? null,
            'base_color_id' => $snapshot['color']['base']['id'] ?? null,
            'accent_color_id' => $snapshot['color']['accent']['id'] ?? null,
            'insert_ids' => collect($snapshot['inserts'] ?? [])->pluck('id')->all(),
        ]);
        $depositDate = CarbonImmutable::parse($item->order->deposit_paid_at ?? $item->created_at)
            ->setTimezone((string) config('studio.timezone', 'Africa/Cairo'));
        $estimatedFinish = $this->estimator->estimate($configuration, $depositDate);

        return $estimatedFinish->greaterThan($item->order->ready_by)
            || ($item->planned_finish !== null && $item->planned_finish->greaterThan($item->order->ready_by));
    }
}
