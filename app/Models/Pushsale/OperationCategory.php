<?php

namespace App\Models\Pushsale;

class OperationCategory extends BusinessRecord
{
    protected $table = 'operation_categories';

    protected $fillable = [
        'name',
        'sort_order',
        'is_start',
        'is_pool',
        'duration_minutes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_start' => 'boolean',
            'is_pool' => 'boolean',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
