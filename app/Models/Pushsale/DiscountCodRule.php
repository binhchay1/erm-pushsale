<?php

namespace App\Models\Pushsale;

class DiscountCodRule extends BusinessRecord
{
    protected $table = 'discount_cod_rules';

    protected $fillable = [
        'rule_type',
        'order_from',
        'discount_value',
        'calculation_type',
        'cod_from',
        'cod_to',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => 'string',
            'order_from' => 'integer',
            'discount_value' => 'integer',
            'cod_from' => 'integer',
            'cod_to' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
