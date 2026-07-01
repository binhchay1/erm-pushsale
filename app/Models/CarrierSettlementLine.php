<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierSettlementLine extends Model
{
    use BelongsToTenant;

    public const MATCH_PENDING = 'pending';

    public const MATCH_MATCHED = 'matched';

    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_AMBIGUOUS = 'ambiguous';

    protected $fillable = [
        'batch_id', 'order_id', 'provider', 'settlement_code', 'transaction_code',
        'tracking_number', 'partner_order_code', 'cod_amount', 'carrier_fee', 'net_amount',
        'match_status', 'match_method', 'settled_at', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'settled_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CarrierSettlementBatch::class, 'batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
