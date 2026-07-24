<?php

namespace App\Models\Pushsale;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceProductLink extends Model
{
    protected $fillable = [
        'shop_connection_id',
        'platform',
        'warehouse_id',
        'external_product_id',
        'external_sku_id',
        'external_name',
        'external_sku',
        'external_attributes',
        'product_id',
        'product_sku',
        'sync_quantity',
        'connection_status',
        'note',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'external_attributes' => 'array',
            'sync_quantity' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(EcommerceShopConnection::class, 'shop_connection_id');
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
