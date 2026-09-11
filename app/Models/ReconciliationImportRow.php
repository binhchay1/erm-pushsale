<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationImportRow extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'batch_id', 'company_id', 'order_id', 'order_code', 'tracking_number',
        'reconciliation_status_raw', 'reconciliation_status', 'note',
        'process_status', 'result_status', 'message', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ReconciliationImportBatch::class, 'batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
