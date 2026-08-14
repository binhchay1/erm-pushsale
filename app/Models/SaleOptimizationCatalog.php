<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOptimizationCatalog extends Model
{
    protected $fillable = [
        'company_id',
        'leader_user_id',
        'name',
        'metrics',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'leader_user_id' => 'integer',
            'metrics' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
