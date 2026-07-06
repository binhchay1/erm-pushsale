<?php

namespace App\Services\Leads;

use App\Models\Order;

/**
 * Cửa sổ chờ upsale trang cảm ơn sau khi đơn đã được tạo & chia số.
 *
 * Trong lúc hold: upsale webhook vẫn gộp vào đơn. Sale chỉnh sửa đơn → khóa,
 * không ghi đè bằng upsale tự động nữa.
 */
final class LandingUpsellService
{
    public function startHold(Order $order): void
    {
        $order->update([
            'landing_upsell_hold_until' => now()->addSeconds($this->holdSeconds()),
            'landing_upsell_locked' => false,
        ]);
    }

    public function extendHold(Order $order): void
    {
        if ($order->isLandingUpsellLocked()) {
            return;
        }

        $order->update([
            'landing_upsell_hold_until' => now()->addSeconds($this->holdSeconds()),
        ]);
    }

    public function releaseHold(Order $order, bool $saleLocked = false): void
    {
        $updates = ['landing_upsell_hold_until' => null];

        if ($saleLocked || $order->isLandingUpsellLocked()) {
            $updates['landing_upsell_locked'] = true;
        }

        $order->update($updates);
    }

    public function lockFromSaleAction(Order $order): void
    {
        if (! $order->landing_upsell_hold_until && ! $order->isLandingUpsellLocked()) {
            return;
        }

        $this->releaseHold($order, saleLocked: true);
    }

    public function holdSeconds(): int
    {
        return (int) config('saleops.landing.hold_seconds', 90);
    }

    public function maxHoldSeconds(): int
    {
        return (int) config('saleops.landing.max_hold_seconds', 300);
    }
}
