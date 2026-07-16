<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;

class ReportDirtyDate extends Model
{
    protected $fillable = [
        'company_id', 'metric_date', 'last_reason', 'source_type', 'source_id', 'event_count',
        'attempts', 'next_attempt_at', 'locked_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'next_attempt_at' => 'datetime',
            'locked_at' => 'datetime',
            'event_count' => 'integer',
            'attempts' => 'integer',
        ];
    }
}
