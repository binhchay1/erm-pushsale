<?php

namespace App\Support;

use App\Data\MetricPairData;
use App\Enums\ClosingStatus;
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
        $closedOrders = $orders->filter(
            static fn (Order $order): bool => $order->closed_at !== null
                || (string) $order->closing_status === ClosingStatus::Closed->value,
        );

        $closed = new MetricPairData(
            $closedOrders->count(),
            (int) $closedOrders->sum(fn (Order $order): int => $order->netRevenue()),
        );

        $confirmed = self::pairForStatuses($closedOrders, [
            DeliveryStatus::WaitingWaybill,
            DeliveryStatus::Delivering,
            DeliveryStatus::Delivered,
            DeliveryStatus::Paid,
            DeliveryStatus::DeliverNow,
            DeliveryStatus::PickingUp,
        ]);

        $canceled = self::pairForStatuses($closedOrders, [DeliveryStatus::CancelWaybill]);
        $transferred = self::pairForStatuses($closedOrders, [
            DeliveryStatus::Delivering,
            DeliveryStatus::Delivered,
            DeliveryStatus::Paid,
            DeliveryStatus::Returning,
            DeliveryStatus::Returned,
        ]);
        $returned = self::pairForStatuses($closedOrders, [DeliveryStatus::Returned]);
        $returning = self::pairForStatuses($closedOrders, [DeliveryStatus::Returning]);
        $delivered = self::pairForStatuses($closedOrders, [DeliveryStatus::Delivered]);
        $paid = self::pairForStatuses($closedOrders, [DeliveryStatus::Paid]);
        $successful = self::mergePairs($delivered, $paid);

        $contactOrders = $orders->whereIn('id', LeadContactMetrics::contactOrderIds($orders));
        $contacts = $contactOrders->count();
        $convertedContacts = $contactOrders->filter(
            static fn (Order $order): bool => $order->closed_at !== null
                || (string) $order->closing_status === ClosingStatus::Closed->value,
        )->count();

        $closedItems = $closedOrders->flatMap(
            static fn (Order $order): Collection => $order->items instanceof Collection
                ? $order->items
                : collect($order->items ?? []),
        );
        $upsellItems = $closedItems->filter(static fn ($item): bool => self::isUpsellItem($item));
        $upsellQuantity = (int) $upsellItems->sum(fn ($item): int => (int) ($item->quantity ?? 0));
        $upsellRevenue = (int) $upsellItems->sum(fn ($item): int => method_exists($item, 'lineTotal')
            ? $item->lineTotal()
            : (int) ((int) ($item->quantity ?? 0) * (int) ($item->unit_price ?? 0) - (int) ($item->discount ?? 0))
        );

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
            'closingRate' => self::rate($convertedContacts, $contacts),
            'productCount' => (int) $closedItems->sum('quantity'),
            'upsellQuantity' => $upsellQuantity,
            'upsellRevenue' => $upsellRevenue,
            'upsellRevenueShare' => self::rate($upsellRevenue, $closed->revenue),
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


    private static function isUpsellItem(mixed $item): bool
    {
        $itemType = strtolower((string) ($item->item_type ?? ''));
        $origin = strtolower((string) ($item->origin ?? ''));

        return $itemType === 'upsell'
            || str_contains($origin, 'upsell')
            || str_contains($origin, 'upsale');
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
