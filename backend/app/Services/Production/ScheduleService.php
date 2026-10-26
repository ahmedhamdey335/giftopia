<?php

namespace App\Services\Production;

use App\Models\BlackoutDate;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ScheduleService
{
    public function planOrder(Order $order): void
    {
        $timezone = (string) config('studio.timezone', 'Africa/Cairo');
        $start = CarbonImmutable::parse($order->deposit_paid_at ?? now(), $timezone);
        $capacity = max(1, (int) config('studio.daily_capacity_minutes', 480));
        $workDays = array_map('intval', (array) config('studio.work_days', [7, 1, 2, 3, 4]));
        $blackouts = BlackoutDate::query()->pluck('date')
            ->map(fn ($date): string => CarbonImmutable::parse($date, $timezone)->setTimezone($timezone)->toDateString())
            ->all();
        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->get();
        $existingLoad = [];
        OrderItem::query()->where('order_id', '!=', $order->id)
            ->whereNotNull('schedule_allocations')->get(['schedule_allocations'])
            ->each(function (OrderItem $scheduled) use (&$existingLoad): void {
                foreach ((array) $scheduled->schedule_allocations as $date => $minutes) {
                    $existingLoad[$date] = (int) ($existingLoad[$date] ?? 0) + (int) $minutes;
                }
            });

        foreach ($items as $item) {
            $cursor = $start;
            $remaining = max(1, (int) $item->labor_minutes_total);
            $plannedStart = null;
            $plannedFinish = null;
            $allocations = [];
            for ($attempt = 0; $remaining > 0 && $attempt < 366; $attempt++) {
                $date = $cursor->toDateString();
                if (! in_array($cursor->isoWeekday(), $workDays, true) || in_array($date, $blackouts, true)) {
                    $cursor = $cursor->startOfDay()->addDay();

                    continue;
                }

                $shiftStart = CarbonImmutable::parse($date.' '.config('studio.work_start', '09:00'), $timezone);
                $shiftEnd = CarbonImmutable::parse($date.' '.config('studio.work_end', '17:00'), $timezone);
                $slotStart = $cursor->greaterThan($shiftStart) ? $cursor : $shiftStart;
                if (! $slotStart->lessThan($shiftEnd)) {
                    $cursor = $cursor->startOfDay()->addDay();

                    continue;
                }
                $shiftMinutes = max(0, (int) floor(($shiftEnd->getTimestamp() - $slotStart->getTimestamp()) / 60));
                $available = min($shiftMinutes, max(0, $capacity - (int) ($existingLoad[$date] ?? 0)));
                if ($available === 0) {
                    $cursor = $cursor->startOfDay()->addDay();

                    continue;
                }
                $minutes = min($remaining, $available);
                $plannedStart ??= $slotStart;
                $plannedFinish = $slotStart->addMinutes($minutes);
                $existingLoad[$date] = (int) ($existingLoad[$date] ?? 0) + $minutes;
                $allocations[$date] = (int) ($allocations[$date] ?? 0) + $minutes;
                $remaining -= $minutes;
                if ($remaining > 0) {
                    $cursor = $cursor->startOfDay()->addDay();
                }
            }

            if ($remaining > 0 || $plannedStart === null || $plannedFinish === null) {
                throw new LogicException('The production schedule could not find a working-capacity slot.');
            }

            if ($plannedStart !== null && $plannedFinish !== null) {
                $item->planned_start = $plannedStart;
                $item->planned_finish = $plannedFinish
                    ->addHours((int) ($item->customization['cure_hours'] ?? 0))
                    ->addHours((int) config('studio.packing_buffer_hours', 24));
                $item->schedule_allocations = $allocations;
                $item->save();
            }
        }
    }

    /**
     * @return array{days: list<array<string, mixed>>, blackouts: list<array<string, mixed>>}
     */
    public function calendar(string $from, string $to): array
    {
        $capacity = (int) config('studio.daily_capacity_minutes', 480);
        $fromDate = CarbonImmutable::parse($from);
        $toDate = CarbonImmutable::parse($to);
        $days = [];
        for ($day = $fromDate; $day->lessThanOrEqualTo($toDate); $day = $day->addDay()) {
            $days[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'load_minutes' => 0,
                'capacity_minutes' => $capacity,
                'items_due' => [],
                'items' => [],
            ];
        }
        $items = OrderItem::query()->with('order')->whereNotNull('schedule_allocations')->get();
        foreach ($items as $item) {
            foreach ((array) $item->schedule_allocations as $date => $minutes) {
                if (isset($days[$date])) {
                    $days[$date]['load_minutes'] += (int) $minutes;
                    $days[$date]['items'][] = $item->id;
                }
            }
            $dueDate = $item->order->ready_by->toDateString();
            if (isset($days[$dueDate])) {
                $days[$dueDate]['items_due'][] = $item->id;
            }
        }
        foreach ($days as &$day) {
            $day['items'] = array_values(array_unique($day['items']));
            $day['items_due'] = array_values(array_unique($day['items_due']));
        }
        unset($day);
        $blackouts = DB::table('blackout_dates')
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')->get(['date', 'reason'])->map(fn ($blackout): array => [
                'date' => (string) $blackout->date,
                'reason' => $blackout->reason,
            ])->all();

        return ['days' => array_values($days), 'blackouts' => $blackouts];
    }
}
