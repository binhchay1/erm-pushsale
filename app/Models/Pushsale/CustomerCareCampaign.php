<?php

namespace App\Models\Pushsale;

class CustomerCareCampaign extends BusinessRecord
{
    protected $table = 'customer_care_campaigns';

    protected $fillable = [
        'name',
        'customer_condition',
        'repeat_days',
        'starts_at',
        'ends_at',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'customer_condition' => 'array',
            'repeat_days' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }
}
