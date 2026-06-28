<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use BelongsToTenant;

    public const STATE_PENDING = 'pending';

    public const STATE_SUBMITTED = 'submitted';

    public const STATE_FAILED = 'failed';

    public const STATE_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id', 'provider', 'partner_order_id', 'tracking_number', 'tracking_id',
        'status_id', 'status_text', 'fee', 'insurance_fee', 'transport', 'state',
        'error_message', 'request_payload', 'response_payload',
        'submitted_at', 'cancelled_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ShippingApiLog::class);
    }
}
