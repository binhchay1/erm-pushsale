<?php

namespace App\Services\Shipping\Carriers\Ghn;

use App\Enums\DeliveryStatus;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;

class GhnStatusMapper
{
    public static function fromText(?string $raw): ?DeliveryStatus
    {
        return DeliveryStatusTextMapper::map($raw);
    }
}
