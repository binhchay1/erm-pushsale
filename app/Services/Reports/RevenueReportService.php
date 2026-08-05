<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Support\MarketingMetrics;
use App\Support\MarketingPacketMetrics;
use App\Support\RevenueMetricsCalculator;
use Illuminate\Support\Collection;

class RevenueReportService
{
    public function __construct(
        private readonly ReportQueryService $queries,
    ) {}

    /** @return array<string, mixed> */
    public function forMarketers(ReportFilterData $filter, ?User $viewer = null): array
    {
        $viewer ??= User::query()->where('role', UserRole::Admin)->firstOrFail();
        $users = User::query()->where('role', UserRole::Marketing)->get();

        return $this->buildGrouped($filter, $viewer, $users, 'marketer_user_id', 'marketerId', 'marketerName');
    }

    /** @return array<string, mixed> */
    public function forSales(ReportFilterData $filter, ?User $viewer = null): array
    {
        $viewer ??= User::query()->where('role', UserRole::Admin)->firstOrFail();
        $users = User::query()->where('role', UserRole::Sales)->get();

        return $this->buildGrouped($filter, $viewer, $users, 'sale_user_id', 'saleId', 'saleName');
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<string, mixed>
     */
    private function buildGrouped(ReportFilterData $filter, User $viewer, $users, string $foreignKey, string $idKey, string $nameKey): array
    {
        $allQuery = $this->queries->orders($viewer, $filter)->with('items');
        $allOrders = (clone $allQuery)->get();
        $totalMetrics = RevenueMetricsCalculator::build($allOrders);
        $totalRow = array_merge(['stt' => 0, $idKey => 'total', $nameKey => 'Tổng', 'isTotalRow' => true], $totalMetrics);

        if ($foreignKey === 'marketer_user_id') {
            $allMarketerIds = User::query()->where('role', UserRole::Marketing)->pluck('id')->all();
            $totalRow = array_merge(
                $totalRow,
                $this->marketingContactMetrics($filter, $allOrders, $allMarketerIds),
                $this->marketingKpiForUserIds($allOrders, $allMarketerIds),
            );
        }

        $rows = [$totalRow];

        foreach ($users as $index => $user) {
            $subset = (clone $allQuery)->where($foreignKey, $user->id)->get();
            $metrics = RevenueMetricsCalculator::build($subset);
            $row = array_merge([
                'stt' => $index + 1,
                $idKey => (string) $user->id,
                $nameKey => $user->name,
                'saleUsername' => strstr($user->email, '@', true),
                'isTotalRow' => false,
            ], $metrics);

            if ($foreignKey === 'marketer_user_id') {
                $row = array_merge(
                    $row,
                    $this->marketingContactMetrics($filter, $subset, [(int) $user->id]),
                    $this->marketingKpiForUserIds($subset, [(int) $user->id]),
                );
            }

            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'formulaLegend' => $this->formulaLegend(),
            'marketingKpiLegend' => $foreignKey === 'marketer_user_id' ? $this->marketingKpiLegend() : null,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<int>  $marketerIds
     * @return array<string, int|float>
     */
    private function marketingContactMetrics(ReportFilterData $filter, Collection $orders, array $marketerIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $marketerIds)));
        $contactCounts = MarketingPacketMetrics::effectiveCountsByMarketer($filter, $orders)->only($ids);
        $primaryCounts = MarketingPacketMetrics::effectivePrimaryCountsByMarketer($filter, $orders)->only($ids);
        $upsaleCounts = MarketingPacketMetrics::effectiveUpsaleCountsByMarketer($filter, $orders)->only($ids);
        $contacts = (int) $contactCounts->sum();
        $primaryPackets = (int) $primaryCounts->sum();
        $upsalePackets = (int) $upsaleCounts->sum();
        $closedOrders = $orders->filter(
            static fn (Order $order): bool => $order->closed_at !== null
                || (string) $order->closing_status === \App\Enums\ClosingStatus::Closed->value,
        )->count();

        return [
            'contacts' => $contacts,
            'primaryPackets' => $primaryPackets,
            'upsalePackets' => $upsalePackets,
            'closingRate' => $contacts > 0 ? round($closedOrders / $contacts * 100, 2) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<int>  $marketerIds
     * @return array<string, int|float>
     */
    private function marketingKpiForUserIds($orders, array $marketerIds): array
    {
        $eligible = $orders->whereIn('delivery_status', DeliveryStatus::revenueEligible());

        // Ngân sách có thể nằm ở campaign cha hoặc nguồn con; cộng toàn
        // bộ source thuộc marketer để KPI không bỏ sót chi phí quảng cáo.
        $campaigns = MarketingSource::query()
            ->whereIn('marketer_user_id', $marketerIds)
            ->get();

        $summary = MarketingMetrics::summarize($eligible, $campaigns);

        return [
            'attributedRevenue' => $summary['attributed_revenue'],
            'adSpend' => $summary['ad_spend'],
            'roas' => $summary['roas'],
            'netContribution' => $summary['net_contribution'],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function marketingKpiLegend(): array
    {
        return [
            ['key' => 'attributedRevenue', 'label' => __('reports.marketing_kpi.attributed_revenue')],
            ['key' => 'adSpend', 'label' => __('reports.marketing_kpi.ad_spend')],
            ['key' => 'roas', 'label' => __('reports.marketing_kpi.roas')],
            ['key' => 'netContribution', 'label' => __('reports.marketing_kpi.net_contribution')],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function formulaLegend(): array
    {
        return [
            ['key' => '1', 'label' => __('reports.revenue_legend.closed')],
            ['key' => '2', 'label' => __('reports.revenue_legend.delivery_confirmed')],
            ['key' => '3', 'label' => __('reports.revenue_legend.cancel_waybill')],
            ['key' => '4', 'label' => __('reports.revenue_legend.transferred')],
            ['key' => '5', 'label' => __('reports.revenue_legend.returned')],
            ['key' => '10', 'label' => __('reports.revenue_legend.return_pct')],
            ['key' => '15', 'label' => __('reports.revenue_legend.close_rate')],
        ];
    }
}
