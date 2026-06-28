<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedPartnerOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'platform', 'warehouse_id', 'shop_name', 'partner_order_id', 'error_description',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
