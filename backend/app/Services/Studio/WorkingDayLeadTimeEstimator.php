<?php

namespace App\Services\Studio;

use Carbon\CarbonImmutable;
use LogicException;

final class WorkingDayLeadTimeEstimator implements LeadTimeEstimator
{
    public function estimate(StudioConfiguration $config, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = (string) config('studio.timezone', 'Africa/Cairo');
        $cursor = ($from ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $remaining = $config->totalLaborMinutes();

        while ($remaining > 0) {
            [$cursor, $shiftEnd] = $this->nextWorkingSlot($cursor, $timezone);
            $available = (int) floor(($shiftEnd->getTimestamp() - $cursor->getTimestamp()) / 60);
            if ($available <= 0) {
                $cursor = $shiftEnd->addMinute();

                continue;
            }

            $consumed = min($remaining, $available);
            $cursor = $cursor->addMinutes($consumed);
            $remaining -= $consumed;

            if ($remaining > 0) {
                $cursor = $shiftEnd->addMinute();
            }
        }

        return $cursor
            ->addHours((int) ($config->baseItem?->cure_hours ?? 0))
            ->addHours((int) config('studio.packing_buffer_hours', 24));
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function nextWorkingSlot(CarbonImmutable $after, string $timezone): array
    {
        $workDays = array_map('intval', (array) config('studio.work_days', [7, 1, 2, 3, 4]));
        $workStart = (string) config('studio.work_start', '09:00');
        $workEnd = (string) config('studio.work_end', '17:00');
        $capacity = max(0, (int) config('studio.daily_capacity_minutes', 480));
        $day = $after;

        for ($attempt = 0; $attempt < 14; $attempt++) {
            if (in_array($day->isoWeekday(), $workDays, true)) {
                $start = CarbonImmutable::parse($day->toDateString().' '.$workStart, $timezone);
                $endOfDay = CarbonImmutable::parse($day->toDateString().' '.$workEnd, $timezone);
                $endOfCapacity = $start->addMinutes($capacity);
                $end = $endOfCapacity->lessThan($endOfDay) ? $endOfCapacity : $endOfDay;
                $slot = $after->greaterThan($start) ? $after : $start;

                if ($slot->lessThan($end)) {
                    return [$slot, $end];
                }
            }

            $day = $day->startOfDay()->addDay();
            $after = $day;
        }

        throw new LogicException('No working slot is available within the configured work week.');
    }
}
