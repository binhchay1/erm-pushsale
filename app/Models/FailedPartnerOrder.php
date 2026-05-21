<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedPartnerOrder extends Model
{
    protected $fillable = [
        'platform', 'warehouse_id', 'shop_name', 'partner_order_id', 'error_description',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
