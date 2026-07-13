<?php

namespace App\Models\Pushsale;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Model;

class ProductComboItem extends Model
{
    protected $fillable = ['combo_product_id', 'component_product_id', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer'];
    }

    public function component(): BelongsTo { return $this->belongsTo(Product::class, 'component_product_id'); }
}
