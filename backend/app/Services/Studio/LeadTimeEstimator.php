<?php

namespace App\Services\Studio;

use Carbon\CarbonImmutable;

interface LeadTimeEstimator
{
    public function estimate(StudioConfiguration $config, ?CarbonImmutable $from = null): CarbonImmutable;
}
