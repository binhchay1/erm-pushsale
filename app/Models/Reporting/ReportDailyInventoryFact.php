<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;

class ReportDailyInventoryFact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metric_date' => 'date'];
    }
}
