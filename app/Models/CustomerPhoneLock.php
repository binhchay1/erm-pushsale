<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPhoneLock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'phone_key',
        'owner_sale_user_id',
        'active_order_id',
        'status',
        'lock_reason',
        'acquired_at',
        'last_activity_at',
        'expires_at',
        'released_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function ownerSale(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_sale_user_id');
    }

    public function activeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'active_order_id');
    }
}
