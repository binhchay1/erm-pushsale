<?php

namespace App\Services\Shipping\Carriers\Ghtk;

use App\Enums\DeliveryStatus;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;

class GhtkStatusMapper
{
    /** @return array{status: ?DeliveryStatus, label: string} */
    public static function fromStatusId(?int $statusId, ?string $statusText = null): array
    {
        $status = match ($statusId) {
            1 => DeliveryStatus::WaitingWaybill,
            2, 12 => DeliveryStatus::PickingUp,
            3, 4, 5 => DeliveryStatus::Delivering,
            6, 7, 45 => DeliveryStatus::Delivered,
            9, 10, 11 => DeliveryStatus::Returning,
            13, 20, 21 => DeliveryStatus::Returned,
            -1, 49 => DeliveryStatus::CancelWaybill,
            default => null,
        };

        $status ??= DeliveryStatusTextMapper::map($statusText);

        return [
            'status' => $status,
            'label' => $statusText ?? ($status?->label() ?? 'Không xác định'),
        ];
    }

    public static function fromText(?string $raw): ?DeliveryStatus
    {
        return DeliveryStatusTextMapper::map($raw);
    }
}
