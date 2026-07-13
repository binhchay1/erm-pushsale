<?php

namespace App\Models\Pushsale;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends BusinessRecord
{
    protected $table = 'product_attribute_values';

    protected $fillable = [
        'product_attribute_id',
        'name',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function attribute(): BelongsTo { return $this->belongsTo(ProductAttribute::class, 'product_attribute_id'); }
}
