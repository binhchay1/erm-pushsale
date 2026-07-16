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
    'reports_live' => env('QUEUE_REPORTS_LIVE', 'reports-live'),
    'reports_history' => env('QUEUE_REPORTS_HISTORY', env('QUEUE_REPORTS', 'reports-history')),
    'reports_archive' => env('QUEUE_REPORTS_ARCHIVE', 'reports-archive'),
    'reports_maintenance' => env('QUEUE_REPORTS_MAINTENANCE', 'reports-maintenance'),
    // Kept for backward compatibility with old deployments. New report jobs use the four queues above.
    'reports' => env('QUEUE_REPORTS', 'reports-history'),
    'exports' => env('QUEUE_EXPORTS', 'exports'),
    'default' => env('QUEUE_DEFAULT_NAMED', 'default'),
];

$supervisor = static function (
    array|string $queue,
    int $minProcesses,
    int $maxProcesses,
    int $timeout,
    int $memory = 128,
    int $tries = 3,
    int $nice = 0,
    int $maxJobs = 1000,
): array {
    return [
        'connection' => 'redis',
        // Horizon respects order in this array. Keep the most urgent queue first.
        'queue' => array_values((array) $queue),
        'balance' => 'auto',
        'autoScalingStrategy' => 'time',
        'minProcesses' => max(1, $minProcesses),
        'maxProcesses' => max(max(1, $minProcesses), $maxProcesses),
        'balanceMaxShift' => 1,
        'balanceCooldown' => 5,
        'maxTime' => 3600,
        'maxJobs' => $maxJobs,
        'memory' => $memory,
        'tries' => $tries,
        'timeout' => $timeout,
        'nice' => $nice,
    ];
};

$production = [
    // Fast ingestion lane. A single autoscaled lane avoids 4-5 idle PHP workers while still separating it from reports/exports.
    'supervisor-ingestion' => $supervisor([
        $queues['webhooks'],
        $queues['pancake_orders'],
        $queues['pancake_chat_sync'],
        $queues['messages'],
    ], (int) env('HORIZON_INGESTION_MIN_PROCESSES', 2), (int) env('HORIZON_INGESTION_MAX_PROCESSES', 8), 180, 192),

    // Carrier APIs and shipment webhooks may block on remote networks, so keep them out of the lead/chat lane.
    'supervisor-shipping' => $supervisor([
        $queues['shipping_webhooks'],
        $queues['shipments'],
    ], (int) env('HORIZON_SHIPPING_MIN_PROCESSES', 1), (int) env('HORIZON_SHIPPING_MAX_PROCESSES', 5), 240, 192),

    // Broadcasts are short jobs. Grouping them removes three always-idle supervisors.
    'supervisor-broadcasts' => $supervisor([
        $queues['internal_chat_broadcasts'],
        $queues['pancake_chat_broadcasts'],
        $queues['dashboard_broadcasts'],
        $queues['notification_broadcasts'],
    ], (int) env('HORIZON_BROADCAST_MIN_PROCESSES', 1), (int) env('HORIZON_BROADCAST_MAX_PROCESSES', 4), 60, 128, 2),

    // Non-critical background work. Translation is kept here with a higher nice value through the supervisor itself.
    'supervisor-background' => $supervisor([
        $queues['notifications'],
        $queues['translations'],
        $queues['default'],
    ], (int) env('HORIZON_BACKGROUND_MIN_PROCESSES', 1), (int) env('HORIZON_BACKGROUND_MAX_PROCESSES', 3), 240, 192, 2, 5),

    // Live report facts for today's dashboard. Keep this small so it never steals CPU from webhooks.
    'supervisor-reports-live' => $supervisor([
        $queues['reports_live'],
    ], (int) env('HORIZON_REPORT_LIVE_MIN_PROCESSES', 1), (int) env('HORIZON_REPORT_LIVE_MAX_PROCESSES', 2), 900, 256, 2, 5, 250),

    // Heavy historical reporting: closed-day facts, checksum verify, snapshot warmup and month archive.
    'supervisor-reports-batch' => $supervisor([
        $queues['reports_history'],
        $queues['reports_maintenance'],
        $queues['reports_archive'],
        $queues['reports'],
    ], (int) env('HORIZON_REPORT_BATCH_MIN_PROCESSES', 1), (int) env('HORIZON_REPORT_BATCH_MAX_PROCESSES', 2), 3600, 384, 2, 10, 100),

    // Exports are user-visible but heavy; keep an isolated low-priority lane.
    'supervisor-exports' => $supervisor([
        $queues['exports'],
    ], (int) env('HORIZON_EXPORT_MIN_PROCESSES', 1), (int) env('HORIZON_EXPORT_MAX_PROCESSES', 2), 900, 384, 2, 10, 100),
];

$local = collect($production)
    ->map(static fn (array $options): array => array_merge($options, [
        'minProcesses' => 1,
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

    // Expose queue names so jobs can use config('horizon.queues.*') safely when needed.
    'queues' => $queues,

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
        'redis:'.$queues['reports_live'] => 180,
        'redis:'.$queues['reports_history'] => 900,
        'redis:'.$queues['reports_archive'] => 1800,
        'redis:'.$queues['reports_maintenance'] => 900,
        'redis:'.$queues['reports'] => 900,
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
