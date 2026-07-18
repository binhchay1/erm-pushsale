<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Settings\FeatureSettingsService;
use Illuminate\Support\Str;

final class OrderCodeGenerator
{
    public function __construct(private readonly FeatureSettingsService $featureSettings) {}

    /**
     * Mã Pushsale chỉ sinh sau khi bản ghi đã có ID và sale xác nhận chốt đơn.
     * Prefix lấy từ Cấu hình chức năng → "Mã đơn prefix"; mặc định PS.
     */
    public function generate(Order $order): string
    {
        if (filled($order->order_code)) {
            return (string) $order->order_code;
        }

        $prefix = Str::of($this->featureSettings->string('SettingMaDonPrefix', 'PS'))
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->limit(6, '')
            ->value();

        $prefix = $prefix !== '' ? $prefix : 'PS';

        return $prefix.str_pad((string) $order->getKey(), 11, '0', STR_PAD_LEFT).'PS';
    }
}
