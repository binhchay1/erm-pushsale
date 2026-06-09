<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventoryMovement extends Model
{
    public const TYPE_INTAKE = 'intake';

    public const TYPE_EXPORT = 'export';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'warehouse_inventory_id',
        'warehouse_id',
        'product_id',
        'user_id',
        'approved_by_user_id',
        'type',
        'quantity',
        'stock_after',
        'reference_type',
        'reference_id',
        'note',
    ];

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_INTAKE => 'Nhập kho',
            self::TYPE_EXPORT => 'Xuất kho',
            self::TYPE_DEDUCTION => 'Xuất theo đơn hàng',
            self::TYPE_RETURN => 'Nhập hàng hoàn',
            default => $type,
        };
    }

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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }
}
