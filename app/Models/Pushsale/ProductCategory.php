<?php

namespace App\Models\Pushsale;

class ProductCategory extends BusinessRecord
{
    protected $table = 'product_categories';

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
