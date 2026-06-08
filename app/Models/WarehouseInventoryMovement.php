<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventoryMovement extends Model
{
    public const TYPE_INTAKE = 'intake';

    public const TYPE_DEDUCTION = 'deduction';

    protected $fillable = [
        'warehouse_inventory_id',
        'warehouse_id',
        'product_id',
        'user_id',
        'type',
        'quantity',
        'stock_after',
        'reference_type',
        'reference_id',
        'note',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }
}
