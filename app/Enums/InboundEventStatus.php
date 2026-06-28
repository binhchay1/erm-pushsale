<?php

namespace App\Enums;

enum InboundEventStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Processed = 'processed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => __('system_monitor.status_received'),
            self::Queued => __('system_monitor.status_queued'),
            self::Processed => __('system_monitor.status_processed'),
            self::Failed => __('system_monitor.status_failed'),
            self::Rejected => __('system_monitor.status_rejected'),
        };
    }
}
