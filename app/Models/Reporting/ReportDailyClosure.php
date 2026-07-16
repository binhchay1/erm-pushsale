<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;

class ReportDailyClosure extends Model
{
    protected $fillable = [
        'company_id', 'metric_date', 'status', 'revision', 'lead_rows', 'order_rows',
        'product_rows', 'cashflow_rows', 'inventory_rows', 'source_checksum', 'facts_checksum',
        'source_watermark_at', 'last_rebuilt_at', 'finalized_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'source_watermark_at' => 'datetime',
            'last_rebuilt_at' => 'datetime',
            'finalized_at' => 'datetime',
            'revision' => 'integer',
        ];
    }
}
