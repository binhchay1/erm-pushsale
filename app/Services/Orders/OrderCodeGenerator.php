<?php

namespace App\Services\Orders;

use App\Models\Order;

final class OrderCodeGenerator
{
    /**
     * Mã Pushsale chỉ sinh sau khi bản ghi đã có ID và sale xác nhận chốt đơn.
     * Dạng PS + 11 chữ số ID + PS giúp mã ổn định, duy nhất và dễ truy vết.
     */
    public function generate(Order $order): string
    {
        if (filled($order->order_code)) {
            return (string) $order->order_code;
        }

        return 'PS'.str_pad((string) $order->getKey(), 11, '0', STR_PAD_LEFT).'PS';
    }
}
