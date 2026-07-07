<?php

namespace App\Support;

use App\Data\MetricPairData;
use App\Enums\DeliveryStatus;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Công thức báo cáo doanh số (1)–(19) — Strategy pattern cho aggregate metrics.
 */
final class RevenueMetricsCalculator
{
    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, mixed>
     */
    public static function build(Collection $orders): array
    {
        $closed = new MetricPairData(
            $orders->count(),
            (int) $orders->sum(fn (Order $o) => $o->netRevenue()),
        );

        $confirmed = self::pairForStatuses($orders, [
            DeliveryStatus::WaitingWaybill,
            DeliveryStatus::Delivering,
            DeliveryStatus::Delivered,
            DeliveryStatus::Paid,
            DeliveryStatus::DeliverNow,
            DeliveryStatus::PickingUp,
        ]);

        $canceled = self::pairForStatuses($orders, [DeliveryStatus::CancelWaybill]);
        $transferred = self::pairForStatuses($orders, [
            DeliveryStatus::Delivering,
            DeliveryStatus::Delivered,
            DeliveryStatus::Paid,
            DeliveryStatus::Returning,
            DeliveryStatus::Returned,
        ]);
        $returned = self::pairForStatuses($orders, [DeliveryStatus::Returned]);
        $returning = self::pairForStatuses($orders, [DeliveryStatus::Returning]);
        $delivered = self::pairForStatuses($orders, [DeliveryStatus::Delivered]);
        $paid = self::pairForStatuses($orders, [DeliveryStatus::Paid]);
        $successful = self::mergePairs($delivered, $paid);

        // 1 contact = 1 khách/đơn trong kỳ, KHÔNG cộng số lần gọi (contact_count).
        $contacts = (int) $orders->count();

        return [
            'closedOrders' => $closed->toArray(),
            'confirmedDelivery' => $confirmed->toArray(),
            'canceledShipping' => $canceled->toArray(),
            'transferredToCarrier' => $transferred->toArray(),
            'returned' => $returned->toArray(),
            'returning' => $returning->toArray(),
            'delivered' => $delivered->toArray(),
            'paid' => $paid->toArray(),
            'successfulDelivery' => $successful->toArray(),
            'returnRate' => self::rate($returned->qty, $transferred->qty),
            'shippingCancelRate' => self::rate($canceled->qty, $closed->qty),
            'confirmRate' => self::rate($confirmed->qty, $closed->qty),
            'successRate' => self::rate($successful->qty, $transferred->qty),
            'contacts' => $contacts,
            'closingRate' => self::rate($closed->qty, $contacts),
            'productCount' => (int) $orders->sum(fn (Order $o) => $o->items->sum('quantity')),
            'averageOrderValue' => $closed->qty > 0 ? (int) round($closed->revenue / $closed->qty) : 0,
            'revenueReturnRate' => self::rate($returned->revenue, $confirmed->revenue),
            'revenueCancelRate' => self::rate($canceled->revenue, $closed->revenue),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<DeliveryStatus>  $statuses
     */
    private static function pairForStatuses(Collection $orders, array $statuses): MetricPairData
    {
        $values = array_map(fn (DeliveryStatus $s) => $s->value, $statuses);

        return self::filterStatus($orders, $values);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<string>  $statusValues
     */
    private static function filterStatus(Collection $orders, array $statusValues): MetricPairData
    {
        $subset = $orders->filter(
            fn (Order $o) => in_array($o->delivery_status, $statusValues, true)
        );

        return new MetricPairData(
            $subset->count(),
            (int) $subset->sum(fn (Order $o) => $o->netRevenue()),
        );
    }

    private static function mergePairs(MetricPairData $a, MetricPairData $b): MetricPairData
    {
        return $a->add($b);
    }

    private static function rate(int|float $part, int|float $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round(($part / $whole) * 100, 1);
    }
}
