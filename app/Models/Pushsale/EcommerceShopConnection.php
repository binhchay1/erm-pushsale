<?php

namespace App\Models\Pushsale;

use App\Models\MarketingSource;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceShopConnection extends Model
{
    protected $fillable = [
        'platform',
        'warehouse_id',
        'marketing_source_id',
        'shop_id',
        'shop_name',
        'logo_url',
        'note',
        'logistics_mode',
        'is_enabled',
        'last_synced_at',
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function marketingSource(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(EcommerceProductLink::class, 'shop_connection_id');
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            'tiktok' => 'TikTok',
            'shopee' => 'Shopee',
            default => strtoupper((string) $this->platform),
        };
    }
}
