<?php

namespace App\Services\Shipping\Gateways\NetShip;

use App\Enums\DeliveryStatus;

/**
 * Map trạng thái NetShip (0..9) → DeliveryStatus nội bộ.
 *
 * @see https://steplap.gitbook.io/netship/truy-xuat-don-hang.md
 */
final class NetShipStatusMapper
{
    /** @return array{status: ?DeliveryStatus, label: string} */
    public static function fromStatusId(int|string|null $statusId, ?string $fallbackText = null): array
    {
        $id = is_numeric($statusId) ? (int) $statusId : null;
        $labels = [
            0 => 'Chờ lấy hàng',
            1 => 'Đang luân chuyển',
            2 => 'Đang giao hàng',
            3 => 'Giao hàng thành công',
            4 => 'Chờ xác nhận giao lại',
            5 => 'Đang hoàn hàng',
            6 => 'Hoàn hàng thành công',
            7 => 'Đơn thất lạc, hư hỏng',
            8 => 'Đơn hủy',
            9 => 'Ngoại lệ',
        ];

        $status = match ($id) {
            0 => DeliveryStatus::PickingUp,
            1 => DeliveryStatus::Posted,
            2 => DeliveryStatus::Delivering,
            3 => DeliveryStatus::Delivered,
            4 => DeliveryStatus::Redelivery,
            5 => DeliveryStatus::Returning,
            6 => DeliveryStatus::Returned,
            7 => DeliveryStatus::CannotDeliver,
            8 => DeliveryStatus::CancelWaybill,
            9 => DeliveryStatus::CannotDeliver,
            default => null,
        };

        return [
            'status' => $status,
            'label' => $labels[$id] ?? ($fallbackText ?: (string) $statusId),
        ];
    }
}
