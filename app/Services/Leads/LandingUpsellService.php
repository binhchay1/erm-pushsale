<?php

namespace App\Services\Leads;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Cửa sổ mở để gộp upsell trang cảm ơn vào đơn Landing vừa tạo.
 *
 * Đây KHÔNG phải delay tạo/chia đơn: đơn được tạo và chia sale ngay. Trong cửa
 * sổ này, gói upsell có cùng session/client-ref/SĐT được phép cộng vào đúng đơn.
 * Hết hạn hoặc sale đã tác nghiệp thì đơn bị khóa và gói đến sau không được gộp.
 */
final class LandingUpsellService
{
    public function startHold(Order $order): void
    {
        $order->update([
            'landing_upsell_hold_until' => $this->nextDeadline($order),
            'landing_upsell_locked' => false,
        ]);
    }

    public function extendHold(Order $order): void
    {
        if (! $this->canMerge($order)) {
            return;
        }

        $order->update([
            'landing_upsell_hold_until' => $this->nextDeadline($order),
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

    /**
     * Kiểm tra tại thời điểm ghi DB để tránh race: lookup thấy còn hạn nhưng tới
     * lúc append item thì cửa sổ đã hết hoặc sale vừa khóa đơn.
     */
    public function canMerge(Order $order, ?Carbon $at = null): bool
    {
        $at ??= now();

        if ($order->isLandingUpsellLocked() || $order->landing_upsell_hold_until === null) {
            return false;
        }

        if (! $order->landing_upsell_hold_until->isAfter($at)) {
            return false;
        }

        return $this->absoluteDeadline($order)->isAfter($at);
    }

    public function holdSeconds(): int
    {
        return max(1, (int) config('saleops.landing.hold_seconds', 90));
    }

    public function maxHoldSeconds(): int
    {
        return max($this->holdSeconds(), (int) config('saleops.landing.max_hold_seconds', 90));
    }

    public function absoluteDeadline(Order $order): Carbon
    {
        $startedAt = $order->created_at?->copy() ?? now();

        return $startedAt->addSeconds($this->maxHoldSeconds());
    }

    private function nextDeadline(Order $order): Carbon
    {
        $rollingDeadline = now()->addSeconds($this->holdSeconds());
        $absoluteDeadline = $this->absoluteDeadline($order);

        return $rollingDeadline->lessThan($absoluteDeadline)
            ? $rollingDeadline
            : $absoluteDeadline;
    }
}
