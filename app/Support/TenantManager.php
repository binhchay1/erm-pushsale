<?php

namespace App\Support;

/**
 * Giữ "công ty hiện tại" + "cửa hàng hiện tại" cho vòng đời request/job.
 *
 * Company:
 * - hasContext = true + id != null  → Eloquent lọc theo company_id.
 * - hasContext = true + id == null  → platform: thấy toàn bộ (không lọc).
 * - hasContext = false              → console/webhook trước khi resolve — không lọc, không tự gán.
 *
 * Shop (chỉ khi model dùng BelongsToShop):
 * - hasShopContext = true + shopId != null → lọc theo shop_id.
 * - hasShopContext = true + shopId == null → all shops trong company.
 * - hasShopContext = false → không lọc shop.
 */
class TenantManager
{
    private ?int $companyId = null;

    private bool $hasContext = false;

    private ?int $shopId = null;

    private bool $hasShopContext = false;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
        $this->hasContext = true;
    }

    public function clear(): void
    {
        $this->companyId = null;
        $this->hasContext = false;
        $this->clearShop();
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function hasContext(): bool
    {
        return $this->hasContext;
    }

    public function setShop(?int $shopId): void
    {
        $this->shopId = $shopId;
        $this->hasShopContext = true;
    }

    public function clearShop(): void
    {
        $this->shopId = null;
        $this->hasShopContext = false;
    }

    public function shopId(): ?int
    {
        return $this->shopId;
    }

    public function hasShopContext(): bool
    {
        return $this->hasShopContext;
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function forCompany(?int $companyId, callable $callback)
    {
        $prevId = $this->companyId;
        $prevHas = $this->hasContext;
        $prevShopId = $this->shopId;
        $prevShopHas = $this->hasShopContext;

        $this->set($companyId);
        // Đổi company → reset shop context để tránh dính shop của company cũ.
        $this->clearShop();

        try {
            return $callback();
        } finally {
            $this->companyId = $prevId;
            $this->hasContext = $prevHas;
            $this->shopId = $prevShopId;
            $this->hasShopContext = $prevShopHas;
        }
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function forShop(?int $shopId, callable $callback)
    {
        $prevId = $this->shopId;
        $prevHas = $this->hasShopContext;

        $this->setShop($shopId);

        try {
            return $callback();
        } finally {
            $this->shopId = $prevId;
            $this->hasShopContext = $prevHas;
        }
    }
}
