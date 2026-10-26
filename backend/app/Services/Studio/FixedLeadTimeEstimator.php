<?php

namespace App\Services\Studio;

use Carbon\CarbonImmutable;

final class FixedLeadTimeEstimator implements LeadTimeEstimator
{
    public function estimate(StudioConfiguration $config, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = (string) config('studio.timezone', 'Africa/Cairo');
        $start = ($from ?? CarbonImmutable::now($timezone))->setTimezone($timezone);

        return $start->addDays((int) config('studio.delivery.lead_time_days', 7));
    }
}
