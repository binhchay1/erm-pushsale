<?php

namespace App\Models\Pushsale;

class CompanySubscriptionHistory extends BusinessRecord
{
    protected $table = 'company_subscription_histories';

    protected $fillable = [
        'payment_code',
        'contract_type',
        'description',
        'amount',
        'paid_at',
        'duration_months',
        'expires_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'amount' => 'integer',
            'duration_months' => 'integer',
        ];
    }
}
