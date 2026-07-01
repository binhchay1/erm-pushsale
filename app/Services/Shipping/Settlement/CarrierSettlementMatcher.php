<?php

namespace App\Services\Shipping\Settlement;

use App\Models\CarrierSettlementLine;
use App\Models\Order;
use App\Models\Shipment;

class CarrierSettlementMatcher
{
    /**
     * @return array{order: ?Order, method: ?string, status: string}
     */
    public function match(string $provider, ?string $trackingNumber, ?string $partnerOrderCode): array
    {
        if ($trackingNumber) {
            $order = Order::query()
                ->where('shipping_provider', $provider)
                ->where('tracking_number', $trackingNumber)
                ->first();

            if ($order) {
                return [
                    'order' => $order,
                    'method' => 'tracking_number',
                    'status' => CarrierSettlementLine::MATCH_MATCHED,
                ];
            }

            $shipment = Shipment::query()
                ->where('provider', $provider)
                ->where('tracking_number', $trackingNumber)
                ->first();

            if ($shipment?->order) {
                return [
                    'order' => $shipment->order,
                    'method' => 'shipment_tracking',
                    'status' => CarrierSettlementLine::MATCH_MATCHED,
                ];
            }
        }

        if ($partnerOrderCode) {
            $order = Order::query()->where('order_code', $partnerOrderCode)->first();
            if ($order) {
                return [
                    'order' => $order,
                    'method' => 'order_code',
                    'status' => CarrierSettlementLine::MATCH_MATCHED,
                ];
            }

            $shipment = Shipment::query()
                ->where('provider', $provider)
                ->where('partner_order_id', $partnerOrderCode)
                ->first();

            if ($shipment?->order) {
                return [
                    'order' => $shipment->order,
                    'method' => 'partner_order_id',
                    'status' => CarrierSettlementLine::MATCH_MATCHED,
                ];
            }
        }

        return [
            'order' => null,
            'method' => null,
            'status' => CarrierSettlementLine::MATCH_UNMATCHED,
        ];
    }
}
