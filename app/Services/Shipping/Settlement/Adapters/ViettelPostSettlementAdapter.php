<?php

namespace App\Services\Shipping\Settlement\Adapters;

use App\Contracts\Shipping\CarrierSettlementAdapterInterface;
use App\Models\Order;
use App\Services\Shipping\Carriers\ViettelPost\ViettelPostApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ViettelPostSettlementAdapter implements CarrierSettlementAdapterInterface
{
    public function __construct(private readonly ViettelPostApiClient $client) {}

    public function provider(): string
    {
        return 'viettel_post';
    }

    public function fetchSettlementLines(Carbon $from, Carbon $to): array
    {
        // Viettel Post chưa expose endpoint bảng kê COD trong client hiện tại.
        // Fallback: quét đơn đã paid trong kỳ và lấy COD từ order nếu hãng đã báo paid qua webhook/sync.
        $rows = [];

        Order::query()
            ->where('shipping_provider', $this->provider())
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('delivery_status', 'paid')
            ->where('amount_to_collect', '>', 0)
            ->get(['id', 'order_code', 'tracking_number', 'amount_to_collect', 'carrier_service_fee', 'updated_at'])
            ->each(function (Order $order) use (&$rows) {
                $rows[] = [
                    'tracking_number' => $order->tracking_number,
                    'partner_order_code' => $order->order_code,
                    'cod_amount' => (int) $order->amount_to_collect,
                    'carrier_fee' => (int) $order->carrier_service_fee,
                    'transaction_code' => 'vtp-paid-'.$order->id,
                    'settled_at' => $order->updated_at?->toDateTimeString(),
                    'raw_payload' => ['source' => 'order_paid_status'],
                ];
            });

        if ($rows === []) {
            Log::info('[Settlement] Viettel Post API settlement chưa có dữ liệu — dùng import CSV hoặc webhook paid.');
        }

        return $rows;
    }
}
