<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingWebhookEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'provider',
        'event_type',
        'partner_order_code',
        'tracking_number',
        'raw_status',
        'mapped_status',
        'partner_cod',
        'system_cod',
        'is_cod_mismatch',
        'order_id',
        'payload',
        'received_at',
        'result',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'is_cod_mismatch' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
