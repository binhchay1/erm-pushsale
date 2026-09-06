<?php

namespace App\Models\Concerns;

use App\Models\Shop;
use App\Models\Scopes\ShopScope;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new ShopScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('shop_id') !== null) {
                return;
            }

            $tenant = app(TenantManager::class);

            if ($tenant->hasShopContext() && $tenant->shopId() !== null) {
                $model->setAttribute('shop_id', $tenant->shopId());
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeWithoutShop(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ShopScope::class);
    }
}
