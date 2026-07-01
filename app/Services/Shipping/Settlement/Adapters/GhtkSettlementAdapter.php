<?php

namespace App\Services\Shipping\Settlement\Adapters;

use App\Contracts\Shipping\CarrierSettlementAdapterInterface;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GhtkSettlementAdapter implements CarrierSettlementAdapterInterface
{
    public function provider(): string
    {
        return 'ghtk';
    }

    public function fetchSettlementLines(Carbon $from, Carbon $to): array
    {
        $rows = [];

        ShippingWebhookEvent::query()
            ->where('provider', $this->provider())
            ->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull('partner_cod')
            ->where('mapped_status', 'paid')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->get()
            ->each(function (ShippingWebhookEvent $event) use (&$rows) {
                $rows[] = [
                    'tracking_number' => $event->tracking_number,
                    'partner_order_code' => $event->partner_order_code,
                    'cod_amount' => (int) $event->partner_cod,
                    'transaction_code' => 'ghtk-webhook-'.$event->id,
                    'settled_at' => $event->received_at?->toDateTimeString(),
                    'raw_payload' => $event->payload,
                ];
            });

        if ($rows === []) {
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
                        'transaction_code' => 'ghtk-paid-'.$order->id,
                        'settled_at' => $order->updated_at?->toDateTimeString(),
                        'raw_payload' => ['source' => 'order_paid_status'],
                    ];
                });
        }

        if ($rows === []) {
            Log::info('[Settlement] GHTK chưa có dòng paid trong kỳ — dùng import CSV hoặc chờ webhook paid.');
        }

        return $rows;
    }
}
