<?php

namespace App\Services\Reports\SalesLeader;

use App\Models\Pushsale\MonthlyKpiPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class SalesTeamReportService
{
    public function __construct(
        private readonly SalesLeaderReportQuery $query,
        private readonly SalesLeaderSaleAggregator $aggregator,
    ) {}

    public function build(Request $request): array
    {
        $orders = $this->query->loadOrders($request);
        $grouped = $this->aggregator->filterGrouped($this->aggregator->groupBySale($orders, $request), $request);
        $now = now();
        $plans = MonthlyKpiPlan::query()
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->get()
            ->keyBy('user_id');

        $rows = $grouped->map(function (array $row, int $index) use ($plans): array {
            $kpiRevenue = (int) ($plans->get($row['id'])?->revenue_target ?? 0);
            $afterDiscount = max(0, $row['provisional_revenue'] - $row['discount']);

            return [
                'index' => $index + 1,
                'sale_id' => $row['id'],
                'sale' => $row['name'],
                'sale_account' => $row['account'],
                'new_contacts' => $row['new_contacts'],
                'new_closed' => $row['new_closed'],
                'new_rate' => round(($row['new_closed'] / max(1, $row['new_contacts'])) * 100, 2),
                'new_products' => $row['new_products'],
                'new_revenue' => $row['new_provisional'],
                'old_contacts' => $row['old_contacts'],
                'old_closed' => $row['old_closed'],
                'old_rate' => round(($row['old_closed'] / max(1, $row['old_contacts'])) * 100, 2),
                'old_products' => $row['old_products'],
                'old_revenue' => $row['old_provisional'],
                'provisional_revenue' => $row['provisional_revenue'],
                'cod_fee' => $row['cod_fee'],
                'cod_support' => $row['cod_support'],
                'discount' => $row['discount'],
                'deposit' => $row['deposit'],
                'after_discount_revenue' => $afterDiscount,
                'kpi_revenue' => $kpiRevenue,
                'kpi_rate' => $kpiRevenue > 0 ? round(($afterDiscount / $kpiRevenue) * 100, 2) : null,
            ];
        })->values();

        $deliveryCards = $this->deliveryCards($orders);
        $totals = $this->totals($rows);
        $page = $this->query->paginateRows($rows, $request);

        return [
            'data' => $page['data'],
            'meta' => $page['meta'],
            'summary' => [
                'totals' => $totals,
                'delivery_cards' => $deliveryCards,
            ],
        ];
    }

    private function deliveryCards(Collection $orders): array
    {
        $waiting = $orders->whereIn('delivery_status', ['waiting_waybill', 'posted'])->count();
        $cancelled = $orders->whereIn('delivery_status', ['cancel_waybill', 'cancel_closing'])->count();
        $delivering = $orders->whereIn('delivery_status', ['picking_up', 'picked_up', 'delivering', 'redelivery'])->count();
        $delivered = $orders->whereIn('delivery_status', ['delivered', 'delivery_complete', 'partial_delivery'])->count();
        $paid = $orders->where('delivery_status', 'paid')->count();
        $returned = $orders->whereIn('delivery_status', ['returned', 'returning'])->count();
        $shippedLike = max(1, $delivered + $paid + $returned);

        return [
            ['key' => 'waiting', 'label' => 'CHỜ GIAO', 'tone' => 'waiting', 'count' => $waiting],
            ['key' => 'cancelled', 'label' => 'HỦY VẬN ĐƠN', 'tone' => 'cancel', 'count' => $cancelled],
            ['key' => 'delivering', 'label' => 'ĐANG GIAO', 'tone' => 'delivering', 'count' => $delivering],
            ['key' => 'delivered', 'label' => 'ĐÃ GIAO', 'tone' => 'delivered', 'count' => $delivered],
            ['key' => 'paid', 'label' => 'ĐÃ THANH TOÁN', 'tone' => 'paid', 'count' => $paid],
            ['key' => 'returned', 'label' => 'ĐÃ HOÀN', 'tone' => 'returned', 'count' => $returned, 'rate' => round(($returned / $shippedLike) * 100, 2)],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function totals(Collection $rows): array
    {
        $keys = [
            'new_contacts', 'new_closed', 'new_products', 'new_revenue',
            'old_contacts', 'old_closed', 'old_products', 'old_revenue',
            'provisional_revenue', 'cod_fee', 'cod_support', 'discount', 'deposit', 'after_discount_revenue', 'kpi_revenue',
        ];
        $totals = ['sale' => 'Tổng', 'sale_account' => ''];
        foreach ($keys as $key) {
            $totals[$key] = 0;
        }
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
        }
        $totals['new_rate'] = round(($totals['new_closed'] / max(1, $totals['new_contacts'])) * 100, 2);
        $totals['old_rate'] = round(($totals['old_closed'] / max(1, $totals['old_contacts'])) * 100, 2);
        $totals['kpi_rate'] = $totals['kpi_revenue'] > 0
            ? round(($totals['after_discount_revenue'] / $totals['kpi_revenue']) * 100, 2)
            : null;

        return $totals;
    }
}
