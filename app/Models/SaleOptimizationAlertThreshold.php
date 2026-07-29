<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOptimizationAlertThreshold extends Model
{
    protected $fillable = [
        'company_id',
        'metric_key',
        'low_ratio',
        'high_ratio',
    ];

    protected function casts(): array
    {
        return [
            'low_ratio' => 'float',
            'high_ratio' => 'float',
        ];
    }
}
