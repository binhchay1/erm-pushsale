<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReturnReceipt extends Model
{
    use BelongsToTenant;

    public const SOURCE_WEBHOOK = 'webhook';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_API_SYNC = 'api_sync';

    protected $fillable = [
        'order_id', 'shipment_id', 'warehouse_id', 'received_by_user_id', 'source',
        'status', 'reason', 'note', 'received_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by_user_id'); }
    public function lines(): HasMany { return $this->hasMany(WarehouseReturnReceiptLine::class, 'receipt_id'); }
}
