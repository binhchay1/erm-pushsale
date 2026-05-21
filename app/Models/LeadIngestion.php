<?php

namespace App\Models;

use App\Enums\LeadIngestionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadIngestion extends Model
{
    protected $fillable = [
        'platform',
        'external_id',
        'status',
        'customer_name',
        'customer_phone',
        'product_interest',
        'utm_source',
        'utm_campaign',
        'payload',
        'order_id',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'status' => LeadIngestionStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
