<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrace extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'order_id', 'shipment_id', 'stage', 'source', 'action',
        'order_code', 'phone', 'external_code', 'partner_order_code', 'gateway_order_id',
        'lookup_code', 'status_code', 'summary', 'payload', 'dedupe_key', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
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
