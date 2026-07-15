<?php

namespace App\Support;

use App\Enums\ReconciliationStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Nguồn sự thật chung để phân loại đơn vào 12 nhóm doanh số Pushsale.
 *
 * Các nhóm có thể chồng lấp có chủ đích. Ví dụ đơn giao thành công thuộc cả
 * tổng, tạm tính, giao thành công và tiếp tục thuộc thực tế/chờ đối soát.
 */
final class OrderRevenueClassifier
{
    /** @var list<string> */
    public const PARTIAL = ['partial', 'partial_delivery', 'delivered_partial', 'partially_delivered'];

    /** @var list<string> */
    public const SUCCESS = ['delivered', 'delivery_complete', 'paid', ...self::PARTIAL];

    /** @var list<string> */
    public const CONFIRMED_EXCLUDED = [
        '',
        'waiting_waybill',
        'cancel_waybill',
        'cancel_closing',
        'postponed',
        'delayed',
        'delay_delivery',
        'delivery_delayed',
    ];

    /** Pushsale mô tả tạm tính loại đã hoàn, hủy vận đơn và hủy đăng đơn. */
    /** @var list<string> */
    public const ESTIMATED_EXCLUDED = ['returned', 'refund', 'cancel_waybill', 'cancel_closing'];

    /** @var list<string> */
    public const CANCELLED = ['cancel_waybill', 'cancel_closing'];

    /** @var list<string> */
    public const RETURNING = ['returning', 'cannot_deliver'];

    /** @var list<string> */
    public const RETURNED = ['returned', 'refund'];

    /** @var list<string> */
    public const TRANSIT = [
        'posted',
        'picking_up',
        'delivering',
        'deliver_now',
        'cannot_deliver',
        'redelivery',
        'returning',
    ];

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, Collection<int, Order>>
     */
    public static function buckets(Collection $orders): array
    {
        $confirmed = $orders->filter(function (Order $order): bool {
            $status = trim((string) $order->delivery_status);

            return ! in_array($status, self::CONFIRMED_EXCLUDED, true);
        });
        $estimated = $orders->reject(fn (Order $order): bool => in_array(
            (string) $order->delivery_status,
            self::ESTIMATED_EXCLUDED,
            true,
        ));
        $success = self::withStatuses($orders, self::SUCCESS);
        $settled = ReconciliationStatus::settledStatuses();

        return [
            'total' => $orders,
            'confirmed' => $confirmed,
            'estimated' => $estimated,
            'discount' => $estimated->filter(fn (Order $order): bool => (int) $order->discount > 0),
            'cancelled' => self::withStatuses($orders, self::CANCELLED),
            'returning' => self::withStatuses($orders, self::RETURNING),
            'returned' => self::withStatuses($orders, self::RETURNED),
            'transit' => self::withStatuses($orders, self::TRANSIT),
            'success' => $success,
            'actual' => $success->filter(fn (Order $order): bool => in_array((string) $order->reconciliation_status, $settled, true)),
            'pending_reconciliation' => $success->reject(fn (Order $order): bool => in_array((string) $order->reconciliation_status, $settled, true)),
            'partial' => self::withStatuses($orders, self::PARTIAL),
        ];
    }

    /**
     * Áp cùng công thức vào query của Marketing Dashboard. `total` không thêm
     * điều kiện; caller chịu trách nhiệm giới hạn các đơn đã lên/chốt.
     */
    public static function applyMode(Builder $query, string $mode): Builder
    {
        return match ($mode) {
            'confirmed' => $query
                ->whereNotNull('delivery_status')
                ->whereNotIn('delivery_status', array_values(array_filter(self::CONFIRMED_EXCLUDED))),
            'temporary', 'estimated' => $query->where(function (Builder $estimated): void {
                $estimated->whereNull('delivery_status')
                    ->orWhereNotIn('delivery_status', self::ESTIMATED_EXCLUDED);
            }),
            'discount' => self::applyMode($query, 'temporary')->where('discount', '>', 0),
            'cancelled' => $query->whereIn('delivery_status', self::CANCELLED),
            'returning' => $query->whereIn('delivery_status', self::RETURNING),
            'returned' => $query->whereIn('delivery_status', self::RETURNED),
            'shipping', 'transit' => $query->whereIn('delivery_status', self::TRANSIT),
            'delivered', 'success' => $query->whereIn('delivery_status', self::SUCCESS),
            'actual' => $query
                ->whereIn('delivery_status', self::SUCCESS)
                ->whereIn('reconciliation_status', ReconciliationStatus::settledStatuses()),
            'reconciling', 'pending_reconciliation' => $query
                ->whereIn('delivery_status', self::SUCCESS)
                ->where(function (Builder $pending): void {
                    $pending->whereNull('reconciliation_status')
                        ->orWhereNotIn('reconciliation_status', ReconciliationStatus::settledStatuses());
                }),
            'partial' => $query->whereIn('delivery_status', self::PARTIAL),
            default => $query,
        };
    }

    /** @param  list<string>  $statuses */
    private static function withStatuses(Collection $orders, array $statuses): Collection
    {
        return $orders->filter(fn (Order $order): bool => in_array((string) $order->delivery_status, $statuses, true));
    }
}
