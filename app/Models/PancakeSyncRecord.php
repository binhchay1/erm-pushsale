<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PancakeSyncRecord extends Model
{
    use BelongsToTenant;

    public const TYPE_ORDER = 'order';

    public const TYPE_LEAD = 'lead';

    public const TYPE_CONVERSATION = 'conversation';

    public const TYPE_CUSTOMER = 'customer';

    protected $fillable = [
        'company_id',
        'integration_connection_id',
        'shop_id',
        'external_type',
        'external_id',
        'external_code',
        'lead_ingestion_id',
        'order_id',
        'status',
        'payload',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function leadIngestion(): BelongsTo
    {
        return $this->belongsTo(LeadIngestion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
