<?php

namespace App\Models\Pushsale;

class SeedingPhoneNumber extends BusinessRecord
{
    protected $table = 'seeding_phone_numbers';

    protected $fillable = [
        'phone',
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
}
