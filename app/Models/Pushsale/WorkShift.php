<?php

namespace App\Models\Pushsale;

class WorkShift extends BusinessRecord
{
    protected $table = 'work_shifts';

    protected $fillable = [
        'name',
        'from_hour',
        'to_hour',
        'note',
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
