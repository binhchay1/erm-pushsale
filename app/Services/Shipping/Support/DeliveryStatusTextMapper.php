<?php

namespace App\Services\Shipping\Support;

use App\Enums\DeliveryStatus;
use Illuminate\Support\Str;

/**
 * Ánh xạ trạng thái giao hàng dạng text (VI/EN) → DeliveryStatus.
 * Thứ tự được sắp từ trạng thái đặc thù đến trạng thái rộng để tránh
 * "đã hoàn" bị hiểu nhầm thành "đang hoàn" hoặc "hoàn tất giao".
 */
final class DeliveryStatusTextMapper
{
    public static function map(?string $raw): ?DeliveryStatus
    {
        if (! filled($raw)) {
            return null;
        }

        $unicode = mb_strtolower(trim((string) $raw));
        $ascii = mb_strtolower(Str::ascii($unicode));
        $has = static fn (array $needles): bool => collect($needles)->contains(
            static fn (string $needle): bool => str_contains($unicode, $needle)
                || str_contains($ascii, Str::ascii($needle)),
        );

        return match (true) {
            $has(['giao một phần', 'giao 1 phần', 'partial delivery', 'partially delivered', 'partial_delivered'])
                => DeliveryStatus::PartialDelivery,

            $has(['đã đối soát', 'đã thanh toán cod', 'cod remitted', 'cod paid', 'reconciled', 'settled', 'paid'])
                => DeliveryStatus::Paid,

            $has(['đã hoàn', 'hoàn thành công', 'hoàn về kho', 'đã trả người gửi', 'returned to sender', 'return completed', 'returned', 'return_success'])
                => DeliveryStatus::Returned,

            $has(['đang hoàn', 'đang chuyển hoàn', 'chuyển hoàn', 'returning', 'return in progress', 'return_to_sender'])
                => DeliveryStatus::Returning,

            $has(['không lấy được hàng', 'lấy hàng thất bại', 'pickup failed', 'cannot pickup', 'pick_failed'])
                => DeliveryStatus::CannotPickup,

            $has(['không giao được', 'giao thất bại', 'delivery failed', 'cannot deliver', 'undeliverable'])
                => DeliveryStatus::CannotDeliver,

            $has(['giao lại', 'đang giao lại', 'redelivery', 're-delivery'])
                => DeliveryStatus::Redelivery,

            $has(['hủy vận đơn', 'đã hủy', 'cancelled', 'canceled', 'cancel'])
                => DeliveryStatus::CancelWaybill,

            $has(['giao thành công', 'đã giao', 'delivery complete', 'delivered', 'completed delivery'])
                => DeliveryStatus::Delivered,

            $has(['đang giao', 'đang vận chuyển', 'in transit', 'delivering', 'out for delivery'])
                => DeliveryStatus::Delivering,

            $has(['đã lấy hàng', 'lấy hàng thành công', 'picked up', 'picked_up'])
                => DeliveryStatus::PickingUp,

            $has(['đang lấy hàng', 'chờ lấy hàng', 'picking up', 'pickup'])
                => DeliveryStatus::PickingUp,

            $has(['đã đăng vận đơn', 'đã tạo vận đơn', 'order created', 'shipment created', 'posted', 'created'])
                => DeliveryStatus::Posted,

            $has(['chờ', 'waiting', 'pending'])
                => DeliveryStatus::WaitingWaybill,

            // Status lạ vẫn được giữ trong timeline; không tự đổi business status của đơn.
            default => null,
        };
    }
}
