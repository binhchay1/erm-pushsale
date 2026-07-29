<?php

namespace App\Services\Reports\SalesLeader;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class SalesLeaderSaleAggregator
{
    public function __construct(private readonly SalesLeaderReportQuery $query) {}

    /** @return Collection<int, array<string, mixed>> */
    public function groupBySale(Collection $orders, ?Request $request = null): Collection
    {
        $stages = SalesLeaderReportQuery::STAGES;
        $request ??= request();

        return $orders->groupBy(fn (Order $order) => $order->sale_user_id ?: 0)->map(function (Collection $saleOrders, int|string $saleId) use ($stages, $request): array {
            /** @var Order $first */
            $first = $saleOrders->first();
            $closed = $saleOrders->whereNotNull('closed_at');
            $new = $saleOrders->filter(fn (Order $order) => ! (bool) $order->is_returning_customer);
            $old = $saleOrders->filter(fn (Order $order) => (bool) $order->is_returning_customer);
            $revenueOf = fn (Order $order) => $this->query->orderRevenue($order, $request);
            $stageMetrics = [];
            foreach ($stages as $stage) {
                $stageOrders = $saleOrders->filter(
                    fn (Order $order) => $this->query->normalizeStage((string) $order->operation_stage) === $stage
                );
                $stageClosed = $stageOrders->whereNotNull('closed_at');
                $stageMetrics[$stage] = [
                    'contacts' => $stageOrders->count(),
                    'untouched' => $stageOrders->filter(fn (Order $order) => $this->query->isStageUntouched($order))->count(),
                    'closed' => $stageClosed->count(),
                    'revenue' => (int) $stageClosed->sum($revenueOf),
                    'products' => (int) $stageClosed->sum(fn (Order $order) => $order->items->sum('quantity')),
                ];
            }

            $delivered = $saleOrders->whereIn('delivery_status', ['delivered', 'delivery_complete', 'paid']);
            $cancelled = $saleOrders->whereIn('delivery_status', ['cancel_waybill', 'cancel_closing', 'cancelled', 'canceled']);
            $returned = $saleOrders->whereIn('delivery_status', ['returned', 'returning']);

            return [
                'id' => (int) $saleId,
                'name' => $first->saleUser?->name ?? 'Chưa phân sale',
                'account' => $this->query->saleAccount($first->saleUser),
                'receive_data' => $this->query->receivesData($first->saleUser),
                '_sale_team_id' => $first->team_id ?? $first->saleUser?->team_id,
                '_sale_leader_id' => $first->team?->leader_user_id,
                'contacts' => $saleOrders->count(),
                'untouched' => $saleOrders->filter(fn (Order $order) => $this->query->isUntouched($order))->count(),
                'closed' => $closed->count(),
                'revenue' => (int) $closed->sum($revenueOf),
                'provisional_revenue' => (int) $saleOrders->sum($revenueOf),
                'delivered_revenue' => (int) $delivered->sum($revenueOf),
                'cancelled_revenue' => (int) $cancelled->sum($revenueOf),
                'returned_revenue' => (int) $returned->sum($revenueOf),
                'products' => (int) $closed->sum(fn (Order $order) => $order->items->sum('quantity')),
                'new_contacts' => $new->count(),
                'new_closed' => $new->whereNotNull('closed_at')->count(),
                'new_products' => (int) $new->whereNotNull('closed_at')->sum(fn (Order $order) => $order->items->sum('quantity')),
                'new_revenue' => (int) $new->whereNotNull('closed_at')->sum($revenueOf),
                'new_provisional' => (int) $new->sum($revenueOf),
                'old_contacts' => $old->count(),
                'old_closed' => $old->whereNotNull('closed_at')->count(),
                'old_products' => (int) $old->whereNotNull('closed_at')->sum(fn (Order $order) => $order->items->sum('quantity')),
                'old_revenue' => (int) $old->whereNotNull('closed_at')->sum($revenueOf),
                'old_provisional' => (int) $old->sum($revenueOf),
                'duplicate_contacts' => $saleOrders->where('is_duplicate_phone', true)->count(),
                'discount' => (int) $saleOrders->sum('discount'),
                'deposit' => (int) $saleOrders->sum('deposit'),
                'cod_fee' => (int) $saleOrders->sum('cod_fee'),
                'cod_support' => (int) $saleOrders->sum('cod_support'),
                'stage_metrics' => $stageMetrics,
                'orders' => $saleOrders,
            ];
        })->values();
    }

    public function filterGrouped(Collection $grouped, Request $request): Collection
    {
        $saleId = (int) ($request->input('sale_id') ?: 0);
        if ($saleId > 0) {
            $grouped = $grouped->where('id', $saleId)->values();
        }

        return $grouped;
    }
}
