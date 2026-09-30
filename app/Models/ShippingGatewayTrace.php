<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingGatewayTrace extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'order_id', 'shipment_id', 'gateway', 'action',
        'external_code', 'partner_order_code', 'gateway_order_id', 'lookup_code',
        'http_status', 'success', 'request_payload', 'response_payload',
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
