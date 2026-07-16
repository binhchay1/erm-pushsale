<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;

class ReportDailyProductFact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metric_date' => 'date', 'is_upsell' => 'boolean'];
    }
}
