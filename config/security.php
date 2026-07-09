<?php

return [
    'csp' => [
        // Bật mặc định cho production. Local vẫn có thể bật bằng SECURITY_CSP_ENABLED=true.
        'enabled' => env('SECURITY_CSP_ENABLED', env('APP_ENV') === 'production'),
        'connect_src' => array_filter(array_map('trim', explode(',', env('SECURITY_CSP_CONNECT_SRC', '')))),
    ],

    'webhook' => [
        'max_payload_kb' => (int) env('WEBHOOK_MAX_PAYLOAD_KB', 512),
    ],
];
