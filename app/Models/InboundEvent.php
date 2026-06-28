<?php

namespace App\Models;

use App\Enums\InboundEventSource;
use App\Enums\InboundEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundEvent extends Model
{
    protected $fillable = [
        'company_id',
        'source',
        'channel',
        'status',
        'http_status',
        'error_message',
        'payload',
        'headers',
        'ip_address',
        'correlation_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
            'source' => InboundEventSource::class,
            'status' => InboundEventStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function markQueued(): void
    {
        $this->update(['status' => InboundEventStatus::Queued]);
    }

    public function markRejected(int $httpStatus, string $message): void
    {
        $this->update([
            'status' => InboundEventStatus::Rejected,
            'http_status' => $httpStatus,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }

    public function markProcessed(): void
    {
        $this->update([
            'status' => InboundEventStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => InboundEventStatus::Failed,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }
}
