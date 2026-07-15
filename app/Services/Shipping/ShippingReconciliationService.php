<?php

namespace App\Services\Shipping;

use App\Enums\ReconciliationStatus;
use App\Models\CarrierSettlementLine;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingWebhookEvent;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Đối soát dòng tiền COD với hãng vận chuyển — theo đơn (order-driven).
 *
 * Trả lời các câu hỏi nghiệp vụ:
 *  - Trong kỳ đẩy bao nhiêu đơn cho hãng?
 *  - Hãng đã trả đủ tiền chưa? Còn thiếu (đã giao nhưng chưa trả) bao nhiêu?
 *  - Đơn nào hoàn về, hoàn sản phẩm gì, giá trị bao nhiêu?
 */
class ShippingReconciliationService
{
    /** Đã trả tiền COD về (hãng đã đối soát/chuyển khoản). */
    private const STATUS_PAID = ['paid'];

    /** Đã giao thành công nhưng chưa thấy tiền về → còn thiếu tiền cần đòi. */
    private const STATUS_DELIVERED = ['delivered', 'delivery_complete', 'partial_delivery', 'partial', 'delivered_partial', 'partially_delivered'];

    /** Đang luân chuyển (chưa kết thúc) → tiền chưa thể đối soát. */
    private const STATUS_TRANSIT = [
        'waiting_waybill', 'posted', 'picking_up', 'deliver_now', 'delivering', 'redelivery',
    ];

    /** Hoàn / trả hàng. */
    private const STATUS_RETURNED = ['returned', 'returning', 'refund'];

    /** Hủy vận đơn / không giao được. */
    private const STATUS_CANCELLED = ['cancel_waybill', 'cancel_closing', 'cannot_deliver', 'cannot_pickup'];

    /**
     * @param  array<string, mixed>  $filter
     * @return array{
     *   total_orders:int, cod_total:int,
     *   cod_paid:int, paid_orders:int,
     *   cod_pending:int, pending_orders:int,
     *   cod_transit:int, transit_orders:int,
     *   returned_orders:int, returned_value:int,
     *   cancelled_orders:int,
     *   cod_mismatch_orders:int, reconciled_orders:int
     * }
     */
    public function summary(array $filter): array
    {
        $paid = $this->sqlList(self::STATUS_PAID);
        $delivered = $this->sqlList(self::STATUS_DELIVERED);
        $transit = $this->sqlList(self::STATUS_TRANSIT);
        $returned = $this->sqlList(self::STATUS_RETURNED);
        $cancelled = $this->sqlList(self::STATUS_CANCELLED);

        /** @var object $row */
        $row = $this->baseQuery($filter)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(amount_to_collect), 0) as cod_total')
            ->selectRaw("COALESCE(SUM(CASE WHEN delivery_status IN ($paid) THEN amount_to_collect ELSE 0 END), 0) as cod_paid")
            ->selectRaw("SUM(CASE WHEN delivery_status IN ($paid) THEN 1 ELSE 0 END) as paid_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN delivery_status IN ($delivered) THEN amount_to_collect ELSE 0 END), 0) as cod_pending")
            ->selectRaw("SUM(CASE WHEN delivery_status IN ($delivered) THEN 1 ELSE 0 END) as pending_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN delivery_status IN ($transit) THEN amount_to_collect ELSE 0 END), 0) as cod_transit")
            ->selectRaw("SUM(CASE WHEN delivery_status IN ($transit) THEN 1 ELSE 0 END) as transit_orders")
            ->selectRaw("SUM(CASE WHEN delivery_status IN ($returned) THEN 1 ELSE 0 END) as returned_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN delivery_status IN ($returned) THEN total ELSE 0 END), 0) as returned_value")
            ->selectRaw("SUM(CASE WHEN delivery_status IN ($cancelled) THEN 1 ELSE 0 END) as cancelled_orders")
            ->selectRaw("SUM(CASE WHEN reconciliation_status IN ('mismatch','short_paid','over_paid') THEN 1 ELSE 0 END) as cod_mismatch_orders")
            ->selectRaw("SUM(CASE WHEN reconciliation_status IN ('settled','reconciled') THEN 1 ELSE 0 END) as reconciled_orders")
            ->selectRaw("SUM(CASE WHEN reconciliation_status = 'settled' OR reconciliation_status = 'reconciled' THEN settled_cod_amount ELSE 0 END) as cod_settled")
            ->selectRaw("SUM(CASE WHEN reconciliation_status = 'short_paid' THEN 1 ELSE 0 END) as short_paid_orders")
            ->selectRaw("SUM(CASE WHEN reconciliation_status = 'missing_settlement' THEN 1 ELSE 0 END) as missing_settlement_orders")
            ->first();

        return [
            'total_orders' => (int) ($row->total_orders ?? 0),
            'cod_total' => (int) ($row->cod_total ?? 0),
            'cod_paid' => (int) ($row->cod_paid ?? 0),
            'paid_orders' => (int) ($row->paid_orders ?? 0),
            'cod_pending' => (int) ($row->cod_pending ?? 0),
            'pending_orders' => (int) ($row->pending_orders ?? 0),
            'cod_transit' => (int) ($row->cod_transit ?? 0),
            'transit_orders' => (int) ($row->transit_orders ?? 0),
            'returned_orders' => (int) ($row->returned_orders ?? 0),
            'returned_value' => (int) ($row->returned_value ?? 0),
            'cancelled_orders' => (int) ($row->cancelled_orders ?? 0),
            'cod_mismatch_orders' => (int) ($row->cod_mismatch_orders ?? 0),
            'reconciled_orders' => (int) ($row->reconciled_orders ?? 0),
            'cod_settled' => (int) ($row->cod_settled ?? 0),
            'short_paid_orders' => (int) ($row->short_paid_orders ?? 0),
            'missing_settlement_orders' => (int) ($row->missing_settlement_orders ?? 0),
            'unmatched_settlement_lines' => $this->unmatchedSettlementCount($filter),
        ];
    }

    /**
     * Phân rã số đơn + COD theo từng trạng thái giao hàng.
     *
     * @param  array<string, mixed>  $filter
     * @return list<array{delivery_status:string, orders:int, cod:int}>
     */
    public function statusBreakdown(array $filter): array
    {
        return $this->baseQuery($filter)
            ->selectRaw('delivery_status, COUNT(*) as orders, COALESCE(SUM(amount_to_collect), 0) as cod')
            ->groupBy('delivery_status')
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($r) => [
                'delivery_status' => (string) $r->delivery_status,
                'orders' => (int) $r->orders,
                'cod' => (int) $r->cod,
            ])
            ->all();
    }

    /**
     * Đơn hoàn về, gom theo sản phẩm.
     *
     * @param  array<string, mixed>  $filter
     * @return list<array{product_id:?int, product_name:string, orders:int, value:int}>
     */
    public function returnsByProduct(array $filter): array
    {
        $rows = $this->baseQuery($filter)
            ->whereIn('delivery_status', self::STATUS_RETURNED)
            ->selectRaw('product_id, COUNT(*) as orders, COALESCE(SUM(total), 0) as value')
            ->groupBy('product_id')
            ->orderByDesc('orders')
            ->get();

        $names = Product::query()
            ->whereIn('id', $rows->pluck('product_id')->filter()->all())
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($r) => [
                'product_id' => $r->product_id ? (int) $r->product_id : null,
                'product_name' => $r->product_id ? ($names[$r->product_id] ?? '#'.$r->product_id) : '—',
                'orders' => (int) $r->orders,
                'value' => (int) $r->value,
            ])
            ->all();
    }

    /**
     * Dòng tiền hãng báo nhưng không map được đơn nội bộ.
     *
     * @param  array<string, mixed>  $filter
     * @return list<array<string, mixed>>
     */
    public function unmatchedSettlements(array $filter, int $limit = 100): array
    {
        [$from, $to] = $this->resolveRange($filter);

        return CarrierSettlementLine::query()
            ->where('match_status', CarrierSettlementLine::MATCH_UNMATCHED)
            ->when(! empty($filter['provider']), fn ($q) => $q->where('provider', $filter['provider']))
            ->whereBetween('created_at', [$from, $to])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (CarrierSettlementLine $line) => [
                'id' => $line->id,
                'provider' => $line->provider,
                'tracking_number' => $line->tracking_number ?: null,
                'partner_order_code' => $line->partner_order_code,
                'cod_amount' => (int) $line->cod_amount,
                'settled_at' => $line->settled_at?->format('d/m/Y H:i'),
                'settlement_code' => $line->settlement_code,
            ])
            ->all();
    }

    /** @param  array<string, mixed>  $filter */
    private function unmatchedSettlementCount(array $filter): int
    {
        [$from, $to] = $this->resolveRange($filter);

        return CarrierSettlementLine::query()
            ->where('match_status', CarrierSettlementLine::MATCH_UNMATCHED)
            ->when(! empty($filter['provider']), fn ($q) => $q->where('provider', $filter['provider']))
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    /**
     * Danh sách đơn để đối soát (có phân trang).
     *
     * @param  array<string, mixed>  $filter
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filter, int $perPage = 25): LengthAwarePaginator
    {
        $paginator = $this->baseQuery($filter)
            ->with(['product:id,name'])
            ->orderByDesc('closed_at')
            ->paginate($perPage)
            ->withQueryString();

        $this->attachPartnerCod($paginator->getCollection());

        return $paginator;
    }

    /**
     * Gắn COD hãng báo (từ webhook gần nhất) cho từng đơn trong trang.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function attachPartnerCod(Collection $orders): void
    {
        $orderIds = $orders->pluck('id')->all();

        if ($orderIds === []) {
            return;
        }

        $latest = ShippingWebhookEvent::query()
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->get(['id', 'order_id', 'partner_cod', 'is_cod_mismatch', 'received_at'])
            ->groupBy('order_id')
            ->map(fn ($group) => $group->first());

        foreach ($orders as $order) {
            $event = $latest->get($order->id);
            $order->setAttribute('partner_cod', $event?->partner_cod);
            $order->setAttribute('has_callback', (bool) $event);
            $order->setAttribute('settled_cod', (int) $order->settled_cod_amount);
        }
    }

    /** @param  array<string, mixed>  $filter */
    private function baseQuery(array $filter): Builder
    {
        [$from, $to] = $this->resolveRange($filter);

        $query = Order::query()
            ->whereNotNull('closed_at')
            ->where(function (Builder $q) {
                $q->whereNotNull('shipping_provider')->orWhereNotNull('tracking_number');
            })
            ->whereBetween('closed_at', [$from, $to]);

        if (! empty($filter['provider'])) {
            $query->where('shipping_provider', $filter['provider']);
        }

        if (! empty($filter['tab']) && $filter['tab'] !== 'overview') {
            $query->where(function (Builder $q) use ($filter) {
                match ($filter['tab']) {
                    'short_paid' => $q->where('reconciliation_status', ReconciliationStatus::ShortPaid->value),
                    'mismatch' => $q->whereIn('reconciliation_status', [
                        ReconciliationStatus::Mismatch->value,
                        ReconciliationStatus::OverPaid->value,
                    ]),
                    'missing' => $q->where('reconciliation_status', ReconciliationStatus::MissingSettlement->value),
                    'returned' => $q->where('reconciliation_status', ReconciliationStatus::Returned->value),
                    'settled' => $q->whereIn('reconciliation_status', ReconciliationStatus::settledStatuses()),
                    default => null,
                };
            });
        }

        if (! empty($filter['recon_status'])) {
            $query->where('reconciliation_status', $filter['recon_status']);
        }

        if (! empty($filter['delivery_status'])) {
            $query->where('delivery_status', $filter['delivery_status']);
        }

        if (! empty($filter['search'])) {
            $term = '%'.$filter['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('order_code', 'like', $term)
                    ->orWhere('tracking_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(array $filter): array
    {
        $type = $filter['period_type'] ?? 'month';
        $now = now();

        return match ($type) {
            'year' => [
                Carbon::create((int) ($filter['year'] ?? $now->year))->startOfYear(),
                Carbon::create((int) ($filter['year'] ?? $now->year))->endOfYear(),
            ],
            'quarter' => $this->quarterRange(
                (int) ($filter['year'] ?? $now->year),
                (int) ($filter['quarter'] ?? (int) ceil($now->month / 3)),
            ),
            'custom' => [
                $this->parseDate($filter['date_from'] ?? null, $now->copy()->startOfMonth())->startOfDay(),
                $this->parseDate($filter['date_to'] ?? null, $now)->endOfDay(),
            ],
            default => $this->monthRange((string) ($filter['month'] ?? $now->format('Y-m'))),
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function monthRange(string $month): array
    {
        $base = Carbon::createFromFormat('Y-m', $month) ?: now();

        return [$base->copy()->startOfMonth(), $base->copy()->endOfMonth()];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function quarterRange(int $year, int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfMonth();

        return [$start, $start->copy()->addMonths(2)->endOfMonth()];
    }

    private function parseDate(mixed $value, Carbon $fallback): Carbon
    {
        return $value ? Carbon::parse($value) : $fallback->copy();
    }

    /** @param  list<string>  $values */
    private function sqlList(array $values): string
    {
        return "'".implode("','", $values)."'";
    }
}
