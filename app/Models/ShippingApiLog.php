<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingApiLog extends Model
{
    protected $fillable = [
        'provider', 'order_id', 'shipment_id', 'action', 'method', 'endpoint',
        'http_status', 'success', 'message', 'request_payload', 'response_payload', 'log_id',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'request_payload' => 'array',
            'response_payload' => 'array',
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
