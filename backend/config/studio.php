<?php

return [
    'quote_ttl_minutes' => (int) env('STUDIO_QUOTE_TTL_MINUTES', 30),
    'daily_capacity_minutes' => (int) env('STUDIO_DAILY_CAPACITY_MINUTES', 480),
    'packing_buffer_hours' => (int) env('STUDIO_PACKING_BUFFER_HOURS', 24),
    'staff_email' => env('STUDIO_STAFF_EMAIL', ''),
    'reporting' => [
        'minimum_samples' => (int) env('STUDIO_REPORT_MINIMUM_SAMPLES', 20),
        'variance_threshold_percent' => (float) env('STUDIO_REPORT_VARIANCE_THRESHOLD_PERCENT', 15),
    ],
    'timezone' => env('STUDIO_TIMEZONE', 'Africa/Cairo'),
    'delivery' => [
        'cities' => ['Tanta', 'طنطا'],
        'governorates' => ['Gharbia', 'الغربية'],
        'fee' => (int) env('STUDIO_DELIVERY_FEE', 0),
        'deposit_percent' => (int) env('STUDIO_DEPOSIT_PERCENT', 50),
        'lead_time_days' => (int) env('STUDIO_LEAD_TIME_DAYS', 7),
        'claim_window_days' => (int) env('STUDIO_CLAIM_WINDOW_DAYS', 3),
        'proof_auto_approve_hours' => (int) env('STUDIO_PROOF_AUTO_APPROVE_HOURS', 48),
        'deposit_hold_hours' => (int) env('STUDIO_DEPOSIT_HOLD_HOURS', 24),
    ],
    'deposit_percent' => (int) env('STUDIO_DEPOSIT_PERCENT', 50),
    'lead_time_days' => (int) env('STUDIO_LEAD_TIME_DAYS', 7),
    // ISO-8601: 1 = Monday ... 7 = Sunday. Sunday–Thursday workshop week.
    'work_days' => [7, 1, 2, 3, 4],
    'work_start' => env('STUDIO_WORK_START', '09:00'),
    'work_end' => env('STUDIO_WORK_END', '17:00'),
    'currency' => 'EGP',
    'money_unit' => 'piaster',
];
