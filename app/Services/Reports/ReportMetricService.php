<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Repositories\ShippingWebhookEventRepository;
use App\Support\OrderRevenue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportMetricService
{
    public function __construct(
        private readonly ReportQueryService $queries,
    ) {}

    /** @return array<string, int|float> */
    public function kpiSummary(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        $leads = $this->queries->leads($user, $filter);
        $closedOrders = (clone $orders)->whereNotNull('closed_at')->count();
        $ordersCount = (clone $orders)->count();

        $revenueBreakdown = OrderRevenue::aggregate($orders);

        return [
            'leads' => (clone $leads)->count(),
            'processed_leads' => (clone $leads)->where('status', LeadIngestionStatus::Processed->value)->count(),
            'failed_leads' => (clone $leads)->where('status', LeadIngestionStatus::Failed->value)->count(),
            'duplicate_leads' => (clone $leads)->where('status', LeadIngestionStatus::Duplicate->value)->count(),
            'orders' => $ordersCount,
            'closed_orders' => $closedOrders,
            'revenue' => $revenueBreakdown['net'],
            'revenue_breakdown' => $revenueBreakdown,
            'conversion_rate' => $this->percentage($closedOrders, $ordersCount),
        ];
    }

    /** @return list<array{label: string, value: int|float}> */
    public function orderSeries(User $user, ReportFilterData $filter, string $sumColumn = 'id'): array
    {
        $orders = $this->queries->orders($user, $filter);
        $dateColumn = $this->queries->dateColumn($filter);

        return $this->days($filter)->map(function (Carbon $day) use ($orders, $dateColumn, $sumColumn) {
            $dayQuery = (clone $orders)->whereDate($dateColumn, $day);

            return [
                'label' => $day->format('d/m'),
                'value' => $sumColumn === 'id' ? $dayQuery->count() : (int) $dayQuery->sum($sumColumn),
            ];
        })->values()->all();
    }

    /** @return list<array{label: string, value: int|float}> */
    public function revenueSeries(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);

        return $this->days($filter)->map(function (Carbon $day) use ($orders) {
            $amount = OrderRevenue::netAmountSql();

            return [
                'label' => $day->format('d/m'),
                'value' => (int) (clone $orders)
                    ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
                    ->whereDate('updated_at', $day)
                    ->selectRaw("SUM({$amount}) as revenue")
                    ->value('revenue'),
            ];
        })->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    public function leadSeries(User $user, ReportFilterData $filter): array
    {
        $leads = $this->queries->leads($user, $filter);

        return $this->days($filter)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => (clone $leads)->whereDate('created_at', $day)->count(),
        ])->values()->all();
    }

    /** @return list<array{name: string, value: int}> */
    public function leadSourceBreakdown(User $user, ReportFilterData $filter, int $limit = 5): array
    {
        return $this->queries->leadsGroupedByPlatform($user, $filter)
            ->orderByDesc('leads_count')
            ->limit($limit)
            ->get()
            ->map(fn (LeadIngestion $lead) => [
                'name' => $lead->platform ?: __('dashboard_data.other'),
                'value' => (int) $lead->leads_count,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    public function funnel(User $user, ReportFilterData $filter): array
    {
        $leads = $this->queries->leads($user, $filter);
        $orders = $this->queries->orders($user, $filter);

        return [
            ['label' => __('dashboard_data.funnel.lead'), 'value' => (clone $leads)->count()],
            ['label' => __('dashboard_data.funnel.allocated'), 'value' => (clone $orders)->whereNotNull('sale_user_id')->count()],
            ['label' => __('dashboard_data.funnel.contacted'), 'value' => (clone $orders)->where('contact_count', '>', 0)->count()],
            ['label' => __('dashboard_data.funnel.closed'), 'value' => (clone $orders)->whereNotNull('closed_at')->count()],
            ['label' => __('dashboard_data.funnel.delivered_paid'), 'value' => (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count()],
        ];
    }

    /** @return list<array{name: string, orders: int, revenue: int, conversion_rate: float}> */
    public function topSales(User $user, ReportFilterData $filter, int $limit = 5): array
    {
        $orders = $this->queries->orders($user, $filter);

        return User::query()
            ->where('role', UserRole::Sales->value)
            ->whereIn('id', (clone $orders)->select('sale_user_id')->whereNotNull('sale_user_id'))
            ->get()
            ->map(function (User $sale) use ($orders) {
                $mine = (clone $orders)->where('sale_user_id', $sale->id);
                $ordersCount = (clone $mine)->count();
                $closedCount = (clone $mine)->whereNotNull('closed_at')->count();
                $eligible = (clone $mine)->whereIn('delivery_status', DeliveryStatus::revenueEligible());

                return [
                    'name' => $sale->name,
                    'orders' => $ordersCount,
                    'revenue' => (int) $eligible->get()->sum(fn (Order $o) => $o->netRevenue()),
                    'conversion_rate' => $this->percentage($closedCount, $ordersCount),
                ];
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<array{name: string, leads: int, orders: int, revenue: int}> */
    public function topSources(User $user, ReportFilterData $filter, int $limit = 5): array
    {
        $orders = $this->queries->orders($user, $filter);
        $leadCounts = $this->queries->leads($user, $filter)
            ->selectRaw('platform, count(*) as leads_count')
            ->groupBy('platform')
            ->pluck('leads_count', 'platform');

        return MarketingSource::query()
            ->whereIn('id', (clone $orders)->select('marketing_source_id')->whereNotNull('marketing_source_id'))
            ->get()
            ->map(function (MarketingSource $source) use ($orders, $leadCounts) {
                $sourceOrders = (clone $orders)->where('marketing_source_id', $source->id);
                $name = $source->utm_source ?: ($source->ad_channel ?: $source->name);

                return [
                    'name' => $name,
                    'leads' => (int) ($leadCounts[$name] ?? $leadCounts[$source->ad_channel] ?? 0),
                    'orders' => (clone $sourceOrders)->count(),
                    'revenue' => (int) (clone $sourceOrders)
                        ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
                        ->get()
                        ->sum(fn (Order $o) => $o->netRevenue()),
                ];
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    public function stageBreakdown(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);

        return collect(OperationStage::cases())->map(fn (OperationStage $stage) => [
            'label' => $stage->label(),
            'value' => (clone $orders)->where('operation_stage', $stage->value)->whereNull('closed_at')->count(),
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /** @return array<string, int> */
    public function warehouseBuckets(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);

        return [
            'waiting_waybill' => (clone $orders)->where('delivery_status', 'waiting_waybill')->count(),
            'pending_export' => (clone $orders)->where('delivery_status', 'picking_up')->count(),
            'delivering' => (clone $orders)->where('delivery_status', 'delivering')->count(),
            'stock_issues' => WarehouseInventory::query()->where('stock_quantity', '<', 10)->count(),
        ];
    }

    /** @return array<string, int> */
    public function accountingBuckets(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);

        return [
            'pending_cod' => (clone $orders)->where('delivery_status', 'delivered')->count(),
            'paid' => (clone $orders)->where('delivery_status', 'paid')->count(),
            'cod_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
            'reconciliation_pending' => (clone $orders)->where('reconciliation_status', 'pending')->count(),
        ];
    }

    /** @return Collection<int, Carbon> */
    private function days(ReportFilterData $filter)
    {
        $from = $filter->dateFrom?->copy()->startOfDay() ?? today();
        $to = $filter->dateTo?->copy()->startOfDay() ?? today();
        $days = max(1, min(31, $from->diffInDays($to) + 1));

        return collect(range(0, $days - 1))->map(fn (int $offset) => $from->copy()->addDays($offset));
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }
}
