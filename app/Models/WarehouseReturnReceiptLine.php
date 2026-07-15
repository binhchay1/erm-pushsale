<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReturnReceiptLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'receipt_id', 'order_item_id', 'product_id', 'expected_quantity', 'received_quantity',
        'restock_quantity', 'damaged_quantity', 'missing_quantity', 'condition', 'note',
    ];

    public function receipt(): BelongsTo { return $this->belongsTo(WarehouseReturnReceipt::class, 'receipt_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
