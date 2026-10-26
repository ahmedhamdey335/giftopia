<?php

$frontendOrigins = array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', ''))));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values($frontendOrigins),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Idempotency-Key'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
