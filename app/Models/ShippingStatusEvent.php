<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingStatusEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'order_id', 'shipment_id', 'provider', 'event_key', 'raw_status', 'mapped_status',
        'location', 'note', 'financials', 'payload', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'financials' => 'array',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
