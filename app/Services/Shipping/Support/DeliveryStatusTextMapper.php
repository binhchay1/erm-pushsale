<?php

namespace App\Services\Shipping\Support;

use App\Enums\DeliveryStatus;

/**
 * Ánh xạ trạng thái giao hàng dạng text (VI/EN) → DeliveryStatus.
 * Dùng chung cho mọi đơn vị vận chuyển để tránh phụ thuộc chéo giữa các carrier.
 */
final class DeliveryStatusTextMapper
{
    public static function map(?string $raw): ?DeliveryStatus
    {
        if (! $raw) {
            return null;
        }

        $value = mb_strtolower($raw);

        return match (true) {
            str_contains($value, 'giao thành công'),
            str_contains($value, 'delivered'),
            str_contains($value, 'hoàn tất') => DeliveryStatus::Delivered,

            str_contains($value, 'đang giao'),
            str_contains($value, 'delivering') => DeliveryStatus::Delivering,

            str_contains($value, 'lấy hàng'),
            str_contains($value, 'pick') => DeliveryStatus::PickingUp,

            str_contains($value, 'hoàn'),
            str_contains($value, 'return') => DeliveryStatus::Returning,

            str_contains($value, 'hủy'),
            str_contains($value, 'cancel') => DeliveryStatus::CancelWaybill,

            str_contains($value, 'chờ'),
            str_contains($value, 'waiting') => DeliveryStatus::WaitingWaybill,

            default => DeliveryStatus::Delivering,
        };
    }
}
