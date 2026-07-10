<?php

$landingOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('LANDING_ALLOWED_ORIGINS', '')),
)));

$landingOrigins = ['*'];

return [
    'paths' => [
        'api/v1/landing/*',
    ],

    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => $landingOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With', 'X-Request-Id'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 600,
    'supports_credentials' => false,
];
