<?php

return [
    'enabled' => env('REPORTING_FACTS_ENABLED', true),
    'timezone' => env('REPORTING_TIMEZONE', env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh')),
    'live_refresh_minutes' => (int) env('REPORTING_LIVE_REFRESH_MINUTES', 5),
    'dirty_batch_size' => (int) env('REPORTING_DIRTY_BATCH_SIZE', 30),
    'close_delay_minutes' => (int) env('REPORTING_CLOSE_DELAY_MINUTES', 20),
    'snapshot_live_ttl_seconds' => (int) env('REPORTING_SNAPSHOT_LIVE_TTL_SECONDS', 300),
    'snapshot_history_ttl_days' => (int) env('REPORTING_SNAPSHOT_HISTORY_TTL_DAYS', 730),
    // Historical facts are rebuilt by SQL aggregation for the exact dirty day/company only.
    // Keep live fallback bounded so many users cannot trigger full raw-table scans at once.
    'max_live_fallback_days' => (int) env('REPORTING_MAX_LIVE_FALLBACK_DAYS', 2),
    'max_detail_live_days' => (int) env('REPORTING_MAX_DETAIL_LIVE_DAYS', 7),
    'archive' => [
        // Hot tables stay in place. Physical copies are yearly (*_YYYY) so we do not
        // multiply schema/tables while data volume is still modest (NVMe + RAM OK).
        'enabled' => env('REPORTING_ARCHIVE_ENABLED', true),
        'driver' => env('REPORTING_ARCHIVE_DRIVER', 'yearly_tables'), // yearly_tables|monthly_tables|cold_records
        'retention_months' => (int) env('REPORTING_HOT_RETENTION_MONTHS', 24),
        'retention_years' => (int) env('REPORTING_HOT_RETENTION_YEARS', 2),
        'allow_purge' => env('REPORTING_ARCHIVE_ALLOW_PURGE', false),
        // Larger chunks = fewer transactions while yearly windows stay small.
        'copy_chunk_size' => (int) env('REPORTING_ARCHIVE_COPY_CHUNK_SIZE', 5000),
        'checksum_chunk_size' => (int) env('REPORTING_ARCHIVE_CHECKSUM_CHUNK_SIZE', 2000),
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
