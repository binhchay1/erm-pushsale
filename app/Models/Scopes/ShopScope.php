<?php

namespace App\Models\Scopes;

use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Lọc theo shop đang chọn trong request.
 * - Không có shop context → không lọc (console/webhook trước khi resolve).
 * - Có context + shopId null → “all shops” (overview / platform).
 * - Có context + shopId → WHERE shop_id = ?
 */
class ShopScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantManager::class);

        if (! $tenant->hasShopContext()) {
            return;
        }

        $shopId = $tenant->shopId();

        if ($shopId === null) {
            return;
        }

        $builder->where($model->getTable().'.shop_id', $shopId);
    }
}
