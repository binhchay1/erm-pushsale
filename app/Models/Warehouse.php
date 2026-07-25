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
        'sort_order', 'use_two_level_address', 'sender_registration_name',
        'sender_print_note', 'default_delivery_provinces',
        'default_shipping_provider', 'default_shipping_service', 'shipping_account_settings',
    ];

    protected function casts(): array
    {
        return [
            'use_two_level_address' => 'boolean',
            'sort_order' => 'integer',
            'shipping_account_settings' => 'array',
        ];
    }

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
