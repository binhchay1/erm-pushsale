<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingSession extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_THANKYOU = 'thankyou';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'marketing_source_id',
        'landing_connection_id',
        'landing_connection_source_id',
        'session_key',
        'customer_phone',
        'status',
        'lead_ingestion_id',
        'order_id',
        'last_activity_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function marketingSource(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class);
    }

    public function landingConnection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class)->withTrashed();
    }

    public function landingConnectionSource(): BelongsTo
    {
        return $this->belongsTo(LandingConnectionSource::class)->withTrashed();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadIngestion::class, 'lead_ingestion_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
