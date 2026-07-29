<?php

namespace App\Services\Leads;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Cửa sổ mở để gộp upsell trang cảm ơn vào đơn Landing vừa tạo.
 *
 * Đây KHÔNG phải delay tạo/chia đơn: đơn được tạo và chia sale ngay. Trong cửa
 * sổ này (mặc định 15 phút), gói cùng SĐT + utm_source nhưng khác URL landing
 * chính được phép cộng vào đúng đơn như upsale. Hết hạn, sale đã tác nghiệp,
 * hoặc gói trùng đúng URL trang chính thì đơn bị khóa / tính trùng số.
 */
final class LandingUpsellService
{
    public function startHold(Order $order, ?Carbon $startedAt = null): bool
    {
        $deadline = $this->nextDeadline($order, $startedAt);

        // Chia thủ công có thể xảy ra sau khi cửa sổ 90 giây tính từ lúc lead
        // về đã hết. Không được mở lại một cửa sổ mới chỉ vì order vừa được tạo.
        if (! $deadline->isAfter(now())) {
            $order->update([
                'landing_upsell_hold_until' => null,
                'landing_upsell_locked' => false,
            ]);

            return false;
        }

        $order->update([
            'landing_upsell_hold_until' => $deadline,
            'landing_upsell_locked' => false,
        ]);

        return true;
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

    public function absoluteDeadline(Order $order, ?Carbon $startedAt = null): Carbon
    {
        $startedAt = $startedAt?->copy() ?? $order->created_at?->copy() ?? now();

        return $startedAt->addSeconds($this->maxHoldSeconds());
    }

    private function nextDeadline(Order $order, ?Carbon $startedAt = null): Carbon
    {
        $rollingDeadline = now()->addSeconds($this->holdSeconds());
        $absoluteDeadline = $this->absoluteDeadline($order, $startedAt);

        return $rollingDeadline->lessThan($absoluteDeadline)
            ? $rollingDeadline
            : $absoluteDeadline;
    }
}
