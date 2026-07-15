<?php

return [
    'paths' => [
        'api/v1/landing/*',
        'api/v1/landing-connections/*',
    ],

    'allowed_methods' => ['POST', 'OPTIONS'],

    /*
     * Hệ thống nhận lead từ nhiều custom-domain Landing. Endpoint không dùng
     * cookie/session (`supports_credentials=false`), vì vậy wildcard origin là
     * phù hợp. CORS không thay thế token, rate-limit hay validation webhook.
     */
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With', 'X-Request-Id'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 600,
    'supports_credentials' => false,
];
