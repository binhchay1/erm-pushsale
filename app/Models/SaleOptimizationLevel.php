<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleOptimizationLevel extends Model
{
    protected $fillable = [
        'company_id',
        'metric_key',
        'label',
        'sort_order',
        'min_ratio',
        'max_ratio',
        'tone',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'min_ratio' => 'float',
            'max_ratio' => 'float',
        ];
    }
}
