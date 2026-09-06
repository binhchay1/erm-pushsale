<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use BelongsToShop, BelongsToTenant;

    protected $fillable = ['shop_id', 'parent_id', 'name', 'type', 'sku', 'unit', 'unit_price', 'cost_price', 'vat_percent', 'vat_code', 'barcode', 'weight_grams', 'length_cm', 'width_cm', 'height_cm', 'warehouse_location', 'is_active', 'available_marketing', 'available_sale', 'available_care', 'marketing_team_ids', 'marketing_user_ids', 'sale_team_ids', 'sale_user_ids', 'care_team_ids', 'care_user_ids'];

    protected $attributes = ['type' => 'product'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_marketing' => 'boolean',
            'available_sale' => 'boolean',
            'available_care' => 'boolean',
            'marketing_team_ids' => 'array',
            'marketing_user_ids' => 'array',
            'sale_team_ids' => 'array',
            'sale_user_ids' => 'array',
            'care_team_ids' => 'array',
            'care_user_ids' => 'array',
            'unit_price' => 'integer',
            'cost_price' => 'integer',
            'vat_percent' => 'decimal:2',
            'weight_grams' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    public function isCombo(): bool
    {
        return $this->type === 'combo';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Pushsale\ProductCategory::class, 'product_category_product');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Pushsale\ProductAttributeValue::class, 'product_attribute_value_product');
    }
}
