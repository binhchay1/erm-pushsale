<?php

return [
    'enabled' => env('REPORTING_FACTS_ENABLED', true),
    'timezone' => env('REPORTING_TIMEZONE', env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh')),
    'live_refresh_minutes' => (int) env('REPORTING_LIVE_REFRESH_MINUTES', 5),
    'dirty_batch_size' => (int) env('REPORTING_DIRTY_BATCH_SIZE', 30),
    'close_delay_minutes' => (int) env('REPORTING_CLOSE_DELAY_MINUTES', 20),
    'snapshot_live_ttl_seconds' => (int) env('REPORTING_SNAPSHOT_LIVE_TTL_SECONDS', 300),
    'snapshot_history_ttl_days' => (int) env('REPORTING_SNAPSHOT_HISTORY_TTL_DAYS', 730),
    'archive' => [
        'enabled' => env('REPORTING_ARCHIVE_ENABLED', true),
        'driver' => env('REPORTING_ARCHIVE_DRIVER', 'monthly_tables'),
        'retention_months' => (int) env('REPORTING_HOT_RETENTION_MONTHS', 6),
        'allow_purge' => env('REPORTING_ARCHIVE_ALLOW_PURGE', false),
        'copy_chunk_size' => (int) env('REPORTING_ARCHIVE_COPY_CHUNK_SIZE', 2000),
        'checksum_chunk_size' => (int) env('REPORTING_ARCHIVE_CHECKSUM_CHUNK_SIZE', 1000),
        'sources' => [
            'lead_ingestions' => ['date_column' => 'created_at', 'mutable' => false, 'purge_safe' => true],
            'inbound_events' => ['date_column' => 'created_at', 'mutable' => false, 'purge_safe' => true],
            'shipping_webhook_events' => ['date_column' => 'received_at', 'mutable' => false, 'purge_safe' => true],
            'shipping_status_events' => ['date_column' => 'created_at', 'mutable' => false, 'purge_safe' => true],
            'activity_logs' => ['date_column' => 'created_at', 'mutable' => false, 'purge_safe' => true],
            'orders' => ['date_column' => 'created_at', 'mutable' => true, 'purge_safe' => false],
            'order_items' => ['date_column' => 'created_at', 'mutable' => true, 'purge_safe' => false],
            'shipments' => ['date_column' => 'created_at', 'mutable' => true, 'purge_safe' => false],
        ],
    ],
];
