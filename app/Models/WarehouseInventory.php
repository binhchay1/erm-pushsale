<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'warehouse_id', 'product_id', 'batch_code', 'expiry_date', 'location_code',
        'uom', 'stock_quantity', 'pending_sales_quantity', 'is_discontinued', 'business_status',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'is_discontinued' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
