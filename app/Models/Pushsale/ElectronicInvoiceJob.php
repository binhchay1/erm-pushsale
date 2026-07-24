<?php

namespace App\Models\Pushsale;

use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectronicInvoiceJob extends BusinessRecord
{
    protected $table = 'electronic_invoice_jobs';

    protected $fillable = [
        'order_id',
        'electronic_invoice_config_id',
        'code_type',
        'process_type',
        'processed_at',
        'status',
        'note',
        'duration_ms',
        'attempts',
        'completed',
        'batch_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
            'completed' => 'boolean',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
