<?php

namespace App\Services\Shops;

use App\Models\Company;
use App\Models\Shop;
use App\Models\User;
use App\Support\TenantManager;
use Illuminate\Support\Facades\DB;

class ShopProvisioningService
{
    /**
     * Tạo shop mặc định cho company mới (hoặc đảm bảo đã có).
     * Gán toàn bộ user hiện có của company vào shop đó.
     */
    public function ensureDefaultShop(Company $company): Shop
    {
        $existing = Shop::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($company): Shop {
            $shop = Shop::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => __('messages.shops.default_name'),
                'code' => 'main',
                'is_default' => true,
                'is_active' => true,
            ]);

            $userIds = User::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->pluck('id')
                ->all();

            if ($userIds !== []) {
                $shop->users()->syncWithoutDetaching($userIds);
                User::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $userIds)
                    ->whereNull('default_shop_id')
                    ->update(['default_shop_id' => $shop->id]);
            }

            return $shop;
        });
    }

    public function createShop(
        Company $company,
        string $name,
        ?string $code = null,
        bool $isDefault = false,
        bool $isActive = true,
        array $userIds = [],
    ): Shop {
        return DB::transaction(function () use ($company, $name, $code, $isDefault, $isActive, $userIds): Shop {
            if ($isDefault) {
                Shop::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $shop = Shop::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => $name,
                'code' => $code ?: Shop::makeCode($name, (int) $company->id),
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ]);

            $attach = array_values(array_unique(array_map('intval', $userIds)));
            if ($attach !== []) {
                $shop->users()->sync($attach);
            }

            return $shop;
        });
    }

    /**
     * Danh sách shop user được phép dùng (admin/owner = tất cả shop active của company).
     *
     * @return list<Shop>
     */
    public function accessibleShopsFor(User $user): array
    {
        if (! $user->company_id) {
            return [];
        }

        $query = Shop::query()
            ->withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (! $this->canAccessAllShops($user)) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query->get()->all();
    }

    public function canAccessAllShops(User $user): bool
    {
        return $user->isAdmin() || $user->isOwner() || $user->isPlatformAdmin();
    }

    public function canAccessShop(User $user, Shop $shop): bool
    {
        if ((int) $shop->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($this->canAccessAllShops($user)) {
            return true;
        }

        return $shop->users()->where('users.id', $user->id)->exists();
    }

    public function resolveCurrentShopId(User $user, ?int $sessionShopId): ?int
    {
        $accessible = collect($this->accessibleShopsFor($user));
        if ($accessible->isEmpty()) {
            $default = $this->ensureDefaultShop($user->company);
            $default->users()->syncWithoutDetaching([$user->id]);
            if (! $user->default_shop_id) {
                $user->forceFill(['default_shop_id' => $default->id])->save();
            }

            return (int) $default->id;
        }

        if ($sessionShopId && $accessible->contains(fn (Shop $s) => (int) $s->id === $sessionShopId)) {
            return $sessionShopId;
        }

        if ($user->default_shop_id && $accessible->contains(fn (Shop $s) => (int) $s->id === (int) $user->default_shop_id)) {
            return (int) $user->default_shop_id;
        }

        $default = $accessible->first(fn (Shop $s) => $s->is_default) ?? $accessible->first();

        return $default ? (int) $default->id : null;
    }
}
