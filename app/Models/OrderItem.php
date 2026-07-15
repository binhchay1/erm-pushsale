<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'item_type', 'origin',
        'quantity', 'unit_price', 'cost_price', 'discount_amount', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'cost_price' => 'integer',
            'discount_amount' => 'integer',
            'quantity' => 'integer',
            'meta' => 'array',
        ];
    }

    /** Thành tiền của dòng sau chiết khấu dòng. */
    public function lineTotal(): int
    {
        return (int) max(0, ((int) $this->unit_price * (int) $this->quantity) - (int) $this->discount_amount);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
