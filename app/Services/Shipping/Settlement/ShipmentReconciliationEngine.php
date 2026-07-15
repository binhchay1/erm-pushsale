<?php

namespace App\Services\Shipping\Settlement;

use App\Enums\DeliveryStatus;
use App\Enums\ReconciliationStatus;
use App\Models\CarrierSettlementLine;
use App\Models\Order;
use Carbon\Carbon;

class ShipmentReconciliationEngine
{
    public const COD_TOLERANCE = 500;

    /** @var list<string> */
    private const TRANSIT = [
        'waiting_waybill', 'posted', 'picking_up', 'deliver_now', 'delivering', 'redelivery',
    ];

    /** @var list<string> */
    private const DELIVERED_AWAITING_PAYMENT = ['delivered', 'delivery_complete', 'partial_delivery', 'partial'];

    /** @var list<string> */
    private const RETURNED = ['returned', 'returning', 'refund'];

    /** @var list<string> */
    private const CANCELLED = ['cancel_waybill', 'cancel_closing', 'cannot_deliver', 'cannot_pickup'];

    public function reconcileOrder(Order $order): ReconciliationStatus
    {
        $order->loadMissing('settlementLines');

        $expectedCod = max(0, (int) $order->amount_to_collect);
        $matchedLines = $order->settlementLines
            ->where('match_status', CarrierSettlementLine::MATCH_MATCHED);
        $settledCod = (int) $matchedLines->sum('cod_amount');
        $carrierFee = (int) $matchedLines->sum('carrier_fee');
        $returnFee = (int) $matchedLines->sum('return_fee');
        $codFee = (int) $matchedLines->sum('cod_fee');
        $otherFee = (int) $matchedLines->sum(fn (CarrierSettlementLine $line) => (int) $line->other_fee + (int) $line->insurance_fee);
        $compensation = (int) $matchedLines->sum('compensation_amount');

        if ($matchedLines->isNotEmpty()) {
            $order->update([
                'carrier_service_fee' => $carrierFee,
                'carrier_return_fee' => $returnFee,
                'cod_fee' => $codFee,
                'carrier_other_fee' => $otherFee,
                'carrier_compensation_amount' => $compensation,
            ]);
        }

        $delivery = (string) $order->delivery_status;

        if (in_array($delivery, self::RETURNED, true)) {
            return $this->apply($order, ReconciliationStatus::Returned, $settledCod);
        }

        if (in_array($delivery, self::CANCELLED, true)) {
            return $this->apply($order, ReconciliationStatus::Pending, $settledCod);
        }

        if ($settledCod > 0) {
            $delta = $settledCod - $expectedCod;

            if (abs($delta) <= self::COD_TOLERANCE) {
                return $this->apply($order, ReconciliationStatus::Settled, $settledCod, now());
            }

            if ($delta < 0) {
                return $this->apply($order, ReconciliationStatus::ShortPaid, $settledCod);
            }

            return $this->apply($order, ReconciliationStatus::OverPaid, $settledCod);
        }

        if (in_array($delivery, self::TRANSIT, true)) {
            return $this->apply($order, ReconciliationStatus::InTransit, 0);
        }

        if (in_array($delivery, [DeliveryStatus::Paid->value], true)) {
            return $this->apply($order, ReconciliationStatus::MissingSettlement, 0);
        }

        if (in_array($delivery, self::DELIVERED_AWAITING_PAYMENT, true)) {
            $closedAt = $order->closed_at;
            if ($closedAt && $closedAt->lt(now()->subDays(14))) {
                return $this->apply($order, ReconciliationStatus::MissingSettlement, 0);
            }

            return $this->apply($order, ReconciliationStatus::Pending, 0);
        }

        return $this->apply($order, ReconciliationStatus::Pending, $settledCod);
    }

    public function codMismatch(?int $systemCod, ?int $partnerCod): bool
    {
        if ($systemCod === null || $partnerCod === null) {
            return false;
        }

        return abs($partnerCod - $systemCod) > self::COD_TOLERANCE;
    }

    private function apply(
        Order $order,
        ReconciliationStatus $status,
        int $settledCod,
        ?Carbon $matchedAt = null,
    ): ReconciliationStatus {
        $order->update([
            'reconciliation_status' => $status->value,
            'settled_cod_amount' => $settledCod,
            'settlement_matched_at' => $matchedAt ?? $order->settlement_matched_at,
        ]);

        return $status;
    }
}
