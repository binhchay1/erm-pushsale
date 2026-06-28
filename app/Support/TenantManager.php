<?php

namespace App\Support;

/**
 * Giữ "công ty hiện tại" cho vòng đời request/job.
 *
 * - hasContext = true + id != null  → mọi truy vấn Eloquent t\u1ef1 l\u1ecdc theo company_id n\u00e0y.
 * - hasContext = true + id == null  → super admin n\u1ec1n t\u1ea3ng: th\u1ea5y to\u00e0n b\u1ed9 (kh\u00f4ng l\u1ecdc).
 * - hasContext = false              → lu\u1ed3ng h\u1ec7 th\u1ed1ng (console/webhook tr\u01b0\u1edbc khi resolve) \u2014 kh\u00f4ng l\u1ecdc, kh\u00f4ng t\u1ef1 g\u00e1n.
 */
class TenantManager
{
    private ?int $companyId = null;

    private bool $hasContext = false;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
        $this->hasContext = true;
    }

    public function clear(): void
    {
        $this->companyId = null;
        $this->hasContext = false;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function hasContext(): bool
    {
        return $this->hasContext;
    }

    /**
     * Ch\u1ea1y callback trong b\u1ed1i c\u1ea3nh c\u1ee7a m\u1ed9t c\u00f4ng ty, sau \u0111\u00f3 kh\u00f4i ph\u1ee5c b\u1ed1i c\u1ea3nh c\u0169.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function forCompany(?int $companyId, callable $callback)
    {
        $prevId = $this->companyId;
        $prevHas = $this->hasContext;

        $this->set($companyId);

        try {
            return $callback();
        } finally {
            $this->companyId = $prevId;
            $this->hasContext = $prevHas;
        }
    }
}
