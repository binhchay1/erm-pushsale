<?php

use Illuminate\Support\Str;

$queues = [
    'webhooks' => env('QUEUE_WEBHOOKS', 'webhooks'),
    'pancake_orders' => env('QUEUE_PANCAKE_ORDERS', 'pancake-orders'),
    'shipping_webhooks' => env('QUEUE_SHIPPING_WEBHOOKS', 'shipping-webhooks'),
    'shipments' => env('QUEUE_SHIPMENTS', 'shipments'),
    'messages' => env('QUEUE_MESSAGES', 'messages'),
    'internal_chat_broadcasts' => env('QUEUE_INTERNAL_CHAT_BROADCASTS', 'broadcasts-internal-chat'),
    'dashboard_broadcasts' => env('QUEUE_DASHBOARD_BROADCASTS', 'broadcasts-dashboard'),
    'notification_broadcasts' => env('QUEUE_NOTIFICATION_BROADCASTS', 'broadcasts-notifications'),
    'pancake_chat_sync' => env('QUEUE_PANCAKE_CHAT_SYNC', 'pancake-chat'),
    'pancake_chat_broadcasts' => env('QUEUE_PANCAKE_CHAT_BROADCASTS', 'broadcasts-pancake-chat'),
    'notifications' => env('QUEUE_NOTIFICATIONS', 'notifications'),
    'translations' => env('QUEUE_TRANSLATIONS', 'translations'),
    'reports' => env('QUEUE_REPORTS', 'reports'),
    'exports' => env('QUEUE_EXPORTS', 'exports'),
    'default' => env('QUEUE_DEFAULT_NAMED', 'default'),
];

$supervisor = static function (
    string $queue,
    int $maxProcesses,
    int $timeout,
    int $memory = 128,
    int $tries = 3,
    int $nice = 0,
): array {
    return [
        'connection' => 'redis',
        'queue' => [$queue],
        'balance' => 'auto',
        'autoScalingStrategy' => 'time',
        'minProcesses' => 1,
        'maxProcesses' => max(1, $maxProcesses),
        'balanceMaxShift' => 1,
        'balanceCooldown' => 3,
        'maxTime' => 3600,
        'maxJobs' => 1000,
        'memory' => $memory,
        'tries' => $tries,
        'timeout' => $timeout,
        'nice' => $nice,
    ];
};

$production = [
    'supervisor-webhooks' => $supervisor($queues['webhooks'], (int) env('HORIZON_WEBHOOK_MAX_PROCESSES', 6), 180),
    'supervisor-pancake-orders' => $supervisor($queues['pancake_orders'], (int) env('HORIZON_PANCAKE_ORDER_MAX_PROCESSES', 4), 180),
    'supervisor-shipping-webhooks' => $supervisor($queues['shipping_webhooks'], (int) env('HORIZON_SHIPPING_WEBHOOK_MAX_PROCESSES', 4), 180),
    'supervisor-shipments' => $supervisor($queues['shipments'], (int) env('HORIZON_SHIPMENT_MAX_PROCESSES', 4), 180),
    'supervisor-messages' => $supervisor($queues['messages'], (int) env('HORIZON_MESSAGE_MAX_PROCESSES', 3), 90),
    'supervisor-internal-chat-broadcasts' => $supervisor($queues['internal_chat_broadcasts'], (int) env('HORIZON_INTERNAL_BROADCAST_MAX_PROCESSES', 3), 60),
    'supervisor-dashboard-broadcasts' => $supervisor($queues['dashboard_broadcasts'], (int) env('HORIZON_DASHBOARD_BROADCAST_MAX_PROCESSES', 3), 60),
    'supervisor-notification-broadcasts' => $supervisor($queues['notification_broadcasts'], (int) env('HORIZON_NOTIFICATION_BROADCAST_MAX_PROCESSES', 3), 60),
    'supervisor-pancake-chat' => $supervisor($queues['pancake_chat_sync'], (int) env('HORIZON_PANCAKE_CHAT_MAX_PROCESSES', 4), 120),
    'supervisor-pancake-chat-broadcasts' => $supervisor($queues['pancake_chat_broadcasts'], (int) env('HORIZON_PANCAKE_BROADCAST_MAX_PROCESSES', 3), 60),
    'supervisor-notifications' => $supervisor($queues['notifications'], (int) env('HORIZON_NOTIFICATION_MAX_PROCESSES', 4), 90),
    'supervisor-translations' => $supervisor($queues['translations'], (int) env('HORIZON_TRANSLATION_MAX_PROCESSES', 2), 180, 192, 2, 5),
    'supervisor-reports' => $supervisor($queues['reports'], (int) env('HORIZON_REPORT_MAX_PROCESSES', 2), 600, 256, 2, 5),
    'supervisor-exports' => $supervisor($queues['exports'], (int) env('HORIZON_EXPORT_MAX_PROCESSES', 2), 900, 384, 2, 10),
    'supervisor-default' => $supervisor($queues['default'], (int) env('HORIZON_DEFAULT_MAX_PROCESSES', 2), 180),
];

$local = collect($production)
    ->map(static fn (array $options): array => array_merge($options, [
        'maxProcesses' => 1,
        'maxTime' => 0,
        'maxJobs' => 0,
    ]))
    ->all();

return [
    'name' => env('HORIZON_NAME', env('APP_NAME', 'ERM SaleOps')),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),

    // Horizon creates an internal Redis connection named "horizon" based on
    // this source connection. The source itself must not be named horizon.
    'use' => env('HORIZON_REDIS_CONNECTION', 'horizon_meta'),

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug((string) env('APP_NAME', 'erm-saleops'), '_').'_horizon:'
    ),

    'middleware' => ['web', 'auth'],

    'waits' => [
        'redis:'.$queues['webhooks'] => 15,
        'redis:'.$queues['pancake_orders'] => 20,
        'redis:'.$queues['shipping_webhooks'] => 20,
        'redis:'.$queues['shipments'] => 30,
        'redis:'.$queues['messages'] => 15,
        'redis:'.$queues['internal_chat_broadcasts'] => 10,
        'redis:'.$queues['dashboard_broadcasts'] => 10,
        'redis:'.$queues['notification_broadcasts'] => 10,
        'redis:'.$queues['pancake_chat_sync'] => 15,
        'redis:'.$queues['pancake_chat_broadcasts'] => 10,
        'redis:'.$queues['notifications'] => 20,
        'redis:'.$queues['translations'] => 120,
        'redis:'.$queues['reports'] => 180,
        'redis:'.$queues['exports'] => 300,
        'redis:'.$queues['default'] => 60,
    ],

    'trim' => [
        'recent' => 120,
        'pending' => 120,
        'completed' => 120,
        'recent_failed' => 10080,
        'failed' => 20160,
        'monitored' => 20160,
    ],

    'silenced' => [],
    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24 * 14,
            'queue' => 24 * 14,
        ],
    ],

    'fast_termination' => (bool) env('HORIZON_FAST_TERMINATION', true),
    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 128),

    'defaults' => [],

    'environments' => [
        'production' => $production,
        'staging' => $production,
        'local' => $local,
        'testing' => $local,
        '*' => $local,
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
