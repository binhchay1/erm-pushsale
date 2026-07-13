<?php

namespace App\Models\Pushsale;

class LeadDistributionRule extends BusinessRecord
{
    protected $table = 'lead_distribution_rules';

    protected $fillable = [
        'name',
        'number_type',
        'recipient_type',
        'allocation_method',
        'product_ids',
        'sale_user_ids',
        'care_user_ids',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'sale_user_ids' => 'array',
            'care_user_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
