<?php

namespace App\Models\Pushsale;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends BusinessRecord
{
    protected $table = 'product_attributes';

    protected $fillable = [
        'name',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'product_attribute_id');
    }
}
