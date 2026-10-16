<?php

return [
    'quote_ttl_minutes' => (int) env('STUDIO_QUOTE_TTL_MINUTES', 30),
    'daily_capacity_minutes' => (int) env('STUDIO_DAILY_CAPACITY_MINUTES', 480),
    'packing_buffer_hours' => (int) env('STUDIO_PACKING_BUFFER_HOURS', 24),
    'timezone' => env('STUDIO_TIMEZONE', 'Africa/Cairo'),
    // ISO-8601: 1 = Monday ... 7 = Sunday. Sunday–Thursday workshop week.
    'work_days' => [7, 1, 2, 3, 4],
    'work_start' => env('STUDIO_WORK_START', '09:00'),
    'work_end' => env('STUDIO_WORK_END', '17:00'),
    'currency' => 'EGP',
    'money_unit' => 'piaster',
];
