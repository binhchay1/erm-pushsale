<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOptimizationTarget extends Model
{
    protected $fillable = [
        'company_id',
        'sale_user_id',
        'metric_key',
        'target_value',
    ];

    protected function casts(): array
    {
        return [
            'sale_user_id' => 'integer',
            'target_value' => 'float',
        ];
    }
}
