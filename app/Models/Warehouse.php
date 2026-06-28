<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name', 'phone', 'address', 'pick_province', 'pick_district', 'pick_ward',
        'manager_user_id', 'vtp_code', 'ghtk_pick_address_id', 'code',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}
