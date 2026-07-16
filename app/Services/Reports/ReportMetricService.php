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
use App\Models\Reporting\ReportDailyCashflowFact;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\Marketing\MarketingBudgetService;
use App\Services\Reporting\ReportFactReader;
use App\Support\OrderRevenue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportMetricService
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly ReportFactReader $facts,
        private readonly MarketingBudgetService $budgets,
    ) {}

    /** @return array<string, int|float|array|string> */
    public function kpiSummary(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawKpiSummary($user, $filter);
        }

        $historical = $this->facts->historicalFilter($filter);
        $live = $this->facts->liveFilter($filter);

        $ordersCount = 0;
        $closedOrders = 0;
        $leadsCount = 0;
        $processedLeads = 0;
        $failedLeads = 0;
        $duplicateLeads = 0;
        $gross = 0;
        $shippingCost = 0;
        $sourceIds = collect();

        if ($historical) {
            $orderFacts = $this->facts->orders($user, $historical);
            $leadFacts = $this->facts->leads($user, $historical);
            $ordersCount += (int) (clone $orderFacts)->sum('order_count');
            $closedOrders += (int) (clone $orderFacts)->sum('closed_order_count');
            $leadsCount += (int) (clone $leadFacts)->sum('lead_count');
            $processedLeads += (int) (clone $leadFacts)->sum('processed_count');
            $failedLeads += (int) (clone $leadFacts)->sum('failed_count');
            $duplicateLeads += (int) (clone $leadFacts)->sum('duplicate_count');
            $gross += (int) (clone $orderFacts)
                ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
                ->sum('order_value');
            $shippingCost += (int) (clone $orderFacts)->sum('closed_shipping_cost');
            $sourceIds = $sourceIds->merge((clone $orderFacts)->where('marketing_source_id', '>', 0)->distinct()->pluck('marketing_source_id'));
        }

        if ($live) {
            $orders = $this->queries->orders($user, $live);
            $leads = $this->queries->leads($user, $live);
            $rawLeads = $this->queries->rawLeads($user, $live);
            $ordersCount += (clone $orders)->count();
            $closedOrders += (clone $orders)->whereNotNull('closed_at')->count();
            $leadsCount += (clone $leads)->count();
            $processedLeads += (clone $leads)->where('status', LeadIngestionStatus::Processed->value)->count();
            $failedLeads += (clone $rawLeads)->where('status', LeadIngestionStatus::Failed->value)->count();
            $duplicateLeads += (clone $rawLeads)->where('status', LeadIngestionStatus::Duplicate->value)->count();
            $gross += (int) (clone $orders)
                ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
                ->selectRaw('SUM('.OrderRevenue::grossAmountSql().') as aggregate_value')
                ->value('aggregate_value');
            $shippingCost += (int) (clone $orders)
                ->whereNotNull('closed_at')
                ->selectRaw('SUM('.OrderRevenue::shippingCostSql().') as aggregate_value')
                ->value('aggregate_value');
            $sourceIds = $sourceIds->merge((clone $orders)
                ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
                ->whereNotNull('marketing_source_id')
                ->distinct()
                ->pluck('marketing_source_id'));
        }

        $sourceIds = $sourceIds->filter()->unique()->values();
        $marketing = ($filter->dateFrom && $filter->dateTo)
            ? $this->budgets->effectiveForSourceIds($sourceIds, $filter->dateFrom, $filter->dateTo)
            : ['amount' => 0, 'actual' => 0, 'planned' => 0, 'basis' => 'none'];
        $net = $gross - $shippingCost - (int) $marketing['amount'];

        return [
            'leads' => $leadsCount,
            'processed_leads' => $processedLeads,
            'failed_leads' => $failedLeads,
            'duplicate_leads' => $duplicateLeads,
            'orders' => $ordersCount,
            'closed_orders' => $closedOrders,
            'revenue' => $net,
            'revenue_breakdown' => [
                'gross' => $gross,
                'shipping_cost' => $shippingCost,
                'marketing_cost' => (int) $marketing['amount'],
                'marketing_actual' => (int) $marketing['actual'],
                'marketing_planned' => (int) $marketing['planned'],
                'marketing_basis' => (string) $marketing['basis'],
                'net' => $net,
            ],
            'aov' => $closedOrders > 0 ? (int) round($net / $closedOrders) : 0,
            'conversion_rate' => $this->percentage($closedOrders, $ordersCount),
        ];
    }

    /** @return list<array{label:string,value:int|float}> */
    public function orderSeries(User $user, ReportFilterData $filter, string $sumColumn = 'id'): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawOrderSeries($user, $filter, $sumColumn);
        }

        $values = [];
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $column = $sumColumn === 'id' ? 'order_count' : ($sumColumn === 'contact_count' ? 'contact_count' : 'order_count');
            $values = $this->facts->orders($user, $historical)
                ->selectRaw("metric_date, SUM({$column}) as aggregate_value")
                ->groupBy('metric_date')
                ->pluck('aggregate_value', 'metric_date')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            foreach ($this->rawOrderSeries($user, $live, $sumColumn) as $row) {
                $day = $this->dayKeyFromLabel($row['label'], $live);
                $values[$day] = ($values[$day] ?? 0) + (int) $row['value'];
            }
        }

        return $this->seriesFromMap($filter, $values);
    }

    /** @return list<array{label:string,value:int|float}> */
    public function revenueSeries(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawRevenueSeries($user, $filter);
        }

        $values = [];
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $values = $this->facts->orders($user, $historical)
                ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
                ->selectRaw('metric_date, SUM(recognized_revenue) as aggregate_value')
                ->groupBy('metric_date')
                ->pluck('aggregate_value', 'metric_date')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            foreach ($this->rawRevenueSeries($user, $live) as $row) {
                $day = $this->dayKeyFromLabel($row['label'], $live);
                $values[$day] = ($values[$day] ?? 0) + (int) $row['value'];
            }
        }

        return $this->seriesFromMap($filter, $values);
    }

    /** @return list<array{label:string,value:int}> */
    public function leadSeries(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawLeadSeries($user, $filter);
        }

        $values = [];
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $values = $this->facts->leads($user, $historical)
                ->selectRaw('metric_date, SUM(lead_count) as aggregate_value')
                ->groupBy('metric_date')
                ->pluck('aggregate_value', 'metric_date')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            foreach ($this->rawLeadSeries($user, $live) as $row) {
                $day = $this->dayKeyFromLabel($row['label'], $live);
                $values[$day] = ($values[$day] ?? 0) + (int) $row['value'];
            }
        }

        return $this->seriesFromMap($filter, $values);
    }

    /** @return list<array{name:string,value:int}> */
    public function leadSourceBreakdown(User $user, ReportFilterData $filter, int $limit = 5): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawLeadSourceBreakdown($user, $filter, $limit);
        }

        $totals = collect();
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $totals = $this->facts->leads($user, $historical)
                ->selectRaw('platform, SUM(lead_count) as aggregate_value')
                ->groupBy('platform')
                ->pluck('aggregate_value', 'platform')
                ->map(fn ($value) => (int) $value);
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            foreach ($this->rawLeadSourceBreakdown($user, $live, 1000) as $row) {
                $totals[$row['name']] = (int) ($totals[$row['name']] ?? 0) + (int) $row['value'];
            }
        }

        return $totals->map(fn ($value, $name) => [
            'name' => $name !== '' ? (string) $name : __('dashboard_data.other'),
            'value' => (int) $value,
        ])->sortByDesc('value')->take($limit)->values()->all();
    }

    /** @return list<array{label:string,value:int}> */
    public function funnel(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawFunnel($user, $filter);
        }

        $leadCount = 0;
        $allocated = 0;
        $contacted = 0;
        $closed = 0;
        $delivered = 0;

        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $leadCount += (int) $this->facts->leads($user, $historical)->sum('lead_count');
            $orders = $this->facts->orders($user, $historical);
            $allocated += (int) (clone $orders)->where('sale_user_id', '>', 0)->sum('order_count');
            $contacted += (int) (clone $orders)->sum('contacted_order_count');
            $closed += (int) (clone $orders)->sum('closed_order_count');
            $delivered += (int) (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->sum('order_count');
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            $leads = $this->queries->leads($user, $live);
            $orders = $this->queries->orders($user, $live);
            $leadCount += (clone $leads)->count();
            $allocated += (clone $orders)->whereNotNull('sale_user_id')->count();
            $contacted += (clone $orders)->where('contact_count', '>', 0)->count();
            $closed += (clone $orders)->whereNotNull('closed_at')->count();
            $delivered += (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count();
        }

        return [
            ['label' => __('dashboard_data.funnel.lead'), 'value' => $leadCount],
            ['label' => __('dashboard_data.funnel.allocated'), 'value' => $allocated],
            ['label' => __('dashboard_data.funnel.contacted'), 'value' => $contacted],
            ['label' => __('dashboard_data.funnel.closed'), 'value' => $closed],
            ['label' => __('dashboard_data.funnel.delivered_paid'), 'value' => $delivered],
        ];
    }

    /** @return list<array{name:string,orders:int,revenue:int,conversion_rate:float}> */
    public function topSales(User $user, ReportFilterData $filter, int $limit = 5): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawTopSales($user, $filter, $limit);
        }

        $metrics = collect();
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $rows = $this->facts->orders($user, $historical)
                ->where('sale_user_id', '>', 0)
                ->selectRaw('sale_user_id, SUM(order_count) as orders_count, SUM(closed_order_count) as closed_count, SUM(recognized_revenue) as revenue_total')
                ->groupBy('sale_user_id')
                ->get();
            foreach ($rows as $row) {
                $metrics[(int) $row->sale_user_id] = [
                    'orders' => (int) $row->orders_count,
                    'closed' => (int) $row->closed_count,
                    'revenue' => (int) $row->revenue_total,
                ];
            }
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            $net = OrderRevenue::netAmountSql('orders');
            $rows = $this->queries->orders($user, $live)
                ->whereNotNull('sale_user_id')
                ->selectRaw('sale_user_id, COUNT(*) as orders_count')
                ->selectRaw('SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END) as closed_count')
                ->selectRaw('SUM(CASE WHEN delivery_status IN ('.collect(DeliveryStatus::revenueEligible())->map(fn ($v) => "'{$v}'")->implode(',').") THEN {$net} ELSE 0 END) as revenue_total")
                ->groupBy('sale_user_id')
                ->get();
            foreach ($rows as $row) {
                $id = (int) $row->sale_user_id;
                $current = $metrics[$id] ?? ['orders' => 0, 'closed' => 0, 'revenue' => 0];
                $metrics[$id] = [
                    'orders' => $current['orders'] + (int) $row->orders_count,
                    'closed' => $current['closed'] + (int) $row->closed_count,
                    'revenue' => $current['revenue'] + (int) $row->revenue_total,
                ];
            }
        }

        $names = User::query()->whereIn('id', $metrics->keys())->pluck('name', 'id');

        return $metrics->map(fn (array $row, int $id) => [
            'name' => (string) ($names[$id] ?? '#'.$id),
            'orders' => $row['orders'],
            'revenue' => $row['revenue'],
            'conversion_rate' => $this->percentage($row['closed'], $row['orders']),
        ])->sortByDesc('revenue')->take($limit)->values()->all();
    }

    /** @return list<array{name:string,leads:int,orders:int,revenue:int}> */
    public function topSources(User $user, ReportFilterData $filter, int $limit = 5): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawTopSources($user, $filter, $limit);
        }

        $metrics = collect();
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $orderRows = $this->facts->orders($user, $historical)
                ->where('marketing_source_id', '>', 0)
                ->selectRaw('marketing_source_id, SUM(order_count) as orders_count, SUM(recognized_revenue) as revenue_total')
                ->groupBy('marketing_source_id')
                ->get();
            foreach ($orderRows as $row) {
                $metrics[(int) $row->marketing_source_id] = [
                    'leads' => 0,
                    'orders' => (int) $row->orders_count,
                    'revenue' => (int) $row->revenue_total,
                ];
            }
            $leadRows = $this->facts->leads($user, $historical)
                ->where('marketing_source_id', '>', 0)
                ->selectRaw('marketing_source_id, SUM(lead_count) as leads_count')
                ->groupBy('marketing_source_id')
                ->get();
            foreach ($leadRows as $row) {
                $id = (int) $row->marketing_source_id;
                $current = $metrics[$id] ?? ['leads' => 0, 'orders' => 0, 'revenue' => 0];
                $current['leads'] += (int) $row->leads_count;
                $metrics[$id] = $current;
            }
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            $net = OrderRevenue::netAmountSql('orders');
            $orderRows = $this->queries->orders($user, $live)
                ->whereNotNull('marketing_source_id')
                ->selectRaw('marketing_source_id, COUNT(*) as orders_count')
                ->selectRaw('SUM(CASE WHEN delivery_status IN ('.collect(DeliveryStatus::revenueEligible())->map(fn ($v) => "'{$v}'")->implode(',').") THEN {$net} ELSE 0 END) as revenue_total")
                ->groupBy('marketing_source_id')
                ->get();
            foreach ($orderRows as $row) {
                $id = (int) $row->marketing_source_id;
                $current = $metrics[$id] ?? ['leads' => 0, 'orders' => 0, 'revenue' => 0];
                $current['orders'] += (int) $row->orders_count;
                $current['revenue'] += (int) $row->revenue_total;
                $metrics[$id] = $current;
            }
            $leadRows = $this->queries->leads($user, $live)
                ->whereNotNull('marketing_source_id')
                ->selectRaw('marketing_source_id, COUNT(*) as leads_count')
                ->groupBy('marketing_source_id')
                ->get();
            foreach ($leadRows as $row) {
                $id = (int) $row->marketing_source_id;
                $current = $metrics[$id] ?? ['leads' => 0, 'orders' => 0, 'revenue' => 0];
                $current['leads'] += (int) $row->leads_count;
                $metrics[$id] = $current;
            }
        }

        $sources = MarketingSource::query()->whereIn('id', $metrics->keys())->get()->keyBy('id');

        return $metrics->map(function (array $row, int $id) use ($sources): array {
            $source = $sources[$id] ?? null;
            return [
                'name' => $source?->utm_source ?: ($source?->ad_channel ?: ($source?->name ?: '#'.$id)),
                'leads' => $row['leads'],
                'orders' => $row['orders'],
                'revenue' => $row['revenue'],
            ];
        })->sortByDesc('revenue')->take($limit)->values()->all();
    }

    /** @return list<array{label:string,value:int}> */
    public function stageBreakdown(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawStageBreakdown($user, $filter);
        }

        $values = collect();
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $values = $this->facts->orders($user, $historical)
                ->where('open_order_count', '>', 0)
                ->selectRaw('operation_stage, SUM(open_order_count) as aggregate_value')
                ->groupBy('operation_stage')
                ->pluck('aggregate_value', 'operation_stage')
                ->map(fn ($value) => (int) $value);
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            $rows = $this->queries->orders($user, $live)
                ->whereNull('closed_at')
                ->selectRaw('operation_stage, COUNT(*) as aggregate_value')
                ->groupBy('operation_stage')
                ->pluck('aggregate_value', 'operation_stage');
            foreach ($rows as $stage => $value) {
                $values[$stage] = (int) ($values[$stage] ?? 0) + (int) $value;
            }
        }

        return collect(OperationStage::cases())->map(fn (OperationStage $stage) => [
            'label' => $stage->label(),
            'value' => (int) ($values[$stage->value] ?? 0),
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /** @return array<string,int> */
    public function warehouseBuckets(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawWarehouseBuckets($user, $filter);
        }

        $values = ['waiting_waybill' => 0, 'pending_export' => 0, 'delivering' => 0];
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $rows = $this->facts->orders($user, $historical)
                ->whereIn('delivery_status', ['waiting_waybill', 'picking_up', 'delivering'])
                ->selectRaw('delivery_status, SUM(order_count) as aggregate_value')
                ->groupBy('delivery_status')
                ->pluck('aggregate_value', 'delivery_status');
            $values['waiting_waybill'] += (int) ($rows['waiting_waybill'] ?? 0);
            $values['pending_export'] += (int) ($rows['picking_up'] ?? 0);
            $values['delivering'] += (int) ($rows['delivering'] ?? 0);
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            $orders = $this->queries->orders($user, $live);
            $values['waiting_waybill'] += (clone $orders)->where('delivery_status', 'waiting_waybill')->count();
            $values['pending_export'] += (clone $orders)->where('delivery_status', 'picking_up')->count();
            $values['delivering'] += (clone $orders)->where('delivery_status', 'delivering')->count();
        }

        return array_merge($values, [
            'stock_issues' => WarehouseInventory::query()->where('stock_quantity', '<', 10)->count(),
        ]);
    }

    /** @return array<string,int> */
    public function accountingBuckets(User $user, ReportFilterData $filter): array
    {
        if (! config('reporting.enabled') || ! $this->facts->supports($filter, $user)) {
            return $this->rawAccountingBuckets($user, $filter);
        }

        $values = ['pending_cod' => 0, 'paid' => 0, 'cod_mismatch' => 0, 'reconciliation_pending' => 0];
        $historical = $this->facts->historicalFilter($filter);
        if ($historical) {
            $orders = $this->facts->orders($user, $historical);
            $values['pending_cod'] += (int) (clone $orders)->where('delivery_status', 'delivered')->sum('order_count');
            $values['paid'] += (int) (clone $orders)->where('delivery_status', 'paid')->sum('order_count');
            $values['reconciliation_pending'] += (int) (clone $orders)->where('reconciliation_status', 'pending')->sum('order_count');
            $values['cod_mismatch'] += (int) ReportDailyCashflowFact::query()
                ->where('company_id', $user->company_id)
                ->whereBetween('metric_date', [$historical->dateFrom->toDateString(), $historical->dateTo->toDateString()])
                ->sum('cod_mismatch_count');
        }

        $live = $this->facts->liveFilter($filter);
        if ($live) {
            $orders = $this->queries->orders($user, $live);
            $values['pending_cod'] += (clone $orders)->where('delivery_status', 'delivered')->count();
            $values['paid'] += (clone $orders)->where('delivery_status', 'paid')->count();
            $values['reconciliation_pending'] += (clone $orders)->where('reconciliation_status', 'pending')->count();
            $values['cod_mismatch'] += \App\Models\ShippingWebhookEvent::query()
                ->where('company_id', $user->company_id)
                ->where('is_cod_mismatch', true)
                ->whereBetween('received_at', [$live->dateFrom, $live->dateTo])
                ->count();
        }

        return $values;
    }

    /** @return array<string,int|float|array|string> */
    private function rawKpiSummary(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        $leads = $this->queries->leads($user, $filter);
        $rawLeads = $this->queries->rawLeads($user, $filter);
        $closedOrders = (clone $orders)->whereNotNull('closed_at')->count();
        $ordersCount = (clone $orders)->count();
        $revenueBreakdown = OrderRevenue::aggregate($orders, $filter->dateFrom, $filter->dateTo);

        return [
            'leads' => (clone $leads)->count(),
            'processed_leads' => (clone $leads)->where('status', LeadIngestionStatus::Processed->value)->count(),
            'failed_leads' => (clone $rawLeads)->where('status', LeadIngestionStatus::Failed->value)->count(),
            'duplicate_leads' => (clone $rawLeads)->where('status', LeadIngestionStatus::Duplicate->value)->count(),
            'orders' => $ordersCount,
            'closed_orders' => $closedOrders,
            'revenue' => $revenueBreakdown['net'],
            'revenue_breakdown' => $revenueBreakdown,
            'aov' => $closedOrders > 0 ? (int) round($revenueBreakdown['net'] / $closedOrders) : 0,
            'conversion_rate' => $this->percentage($closedOrders, $ordersCount),
        ];
    }

    /** @return list<array{label:string,value:int|float}> */
    private function rawOrderSeries(User $user, ReportFilterData $filter, string $sumColumn = 'id'): array
    {
        $orders = $this->queries->orders($user, $filter);
        $dateColumn = $this->queries->dateColumn($filter);

        return $this->days($filter)->map(function (CarbonInterface $day) use ($orders, $dateColumn, $sumColumn) {
            $dayQuery = (clone $orders)->whereDate($dateColumn, $day);
            return [
                'label' => $day->format('d/m'),
                'value' => $sumColumn === 'id' ? $dayQuery->count() : (int) $dayQuery->sum($sumColumn),
            ];
        })->values()->all();
    }

    /** @return list<array{label:string,value:int|float}> */
    private function rawRevenueSeries(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        return $this->days($filter)->map(function (CarbonInterface $day) use ($orders) {
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

    /** @return list<array{label:string,value:int}> */
    private function rawLeadSeries(User $user, ReportFilterData $filter): array
    {
        $leads = $this->queries->leads($user, $filter);
        return $this->days($filter)->map(fn (CarbonInterface $day) => [
            'label' => $day->format('d/m'),
            'value' => (clone $leads)->whereDate('created_at', $day)->count(),
        ])->values()->all();
    }

    /** @return list<array{name:string,value:int}> */
    private function rawLeadSourceBreakdown(User $user, ReportFilterData $filter, int $limit): array
    {
        return $this->queries->leadsGroupedByPlatform($user, $filter)
            ->orderByDesc('leads_count')->limit($limit)->get()
            ->map(fn (LeadIngestion $lead) => [
                'name' => $lead->platform ?: __('dashboard_data.other'),
                'value' => (int) $lead->leads_count,
            ])->values()->all();
    }

    /** @return list<array{label:string,value:int}> */
    private function rawFunnel(User $user, ReportFilterData $filter): array
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

    /** @return list<array{name:string,orders:int,revenue:int,conversion_rate:float}> */
    private function rawTopSales(User $user, ReportFilterData $filter, int $limit): array
    {
        $orders = $this->queries->orders($user, $filter);
        return User::query()->where('role', UserRole::Sales->value)
            ->whereIn('id', (clone $orders)->select('sale_user_id')->whereNotNull('sale_user_id'))
            ->get()->map(function (User $sale) use ($orders) {
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
            })->sortByDesc('revenue')->take($limit)->values()->all();
    }

    /** @return list<array{name:string,leads:int,orders:int,revenue:int}> */
    private function rawTopSources(User $user, ReportFilterData $filter, int $limit): array
    {
        $orders = $this->queries->orders($user, $filter);
        $leadCounts = $this->queries->leads($user, $filter)
            ->selectRaw('platform, count(*) as leads_count')->groupBy('platform')->pluck('leads_count', 'platform');
        return MarketingSource::query()
            ->whereIn('id', (clone $orders)->select('marketing_source_id')->whereNotNull('marketing_source_id'))
            ->get()->map(function (MarketingSource $source) use ($orders, $leadCounts) {
                $sourceOrders = (clone $orders)->where('marketing_source_id', $source->id);
                $name = $source->utm_source ?: ($source->ad_channel ?: $source->name);
                return [
                    'name' => $name,
                    'leads' => (int) ($leadCounts[$name] ?? $leadCounts[$source->ad_channel] ?? 0),
                    'orders' => (clone $sourceOrders)->count(),
                    'revenue' => (int) (clone $sourceOrders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->get()->sum(fn (Order $o) => $o->netRevenue()),
                ];
            })->sortByDesc('revenue')->take($limit)->values()->all();
    }

    /** @return list<array{label:string,value:int}> */
    private function rawStageBreakdown(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        return collect(OperationStage::cases())->map(fn (OperationStage $stage) => [
            'label' => $stage->label(),
            'value' => (clone $orders)->where('operation_stage', $stage->value)->whereNull('closed_at')->count(),
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /** @return array<string,int> */
    private function rawWarehouseBuckets(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        return [
            'waiting_waybill' => (clone $orders)->where('delivery_status', 'waiting_waybill')->count(),
            'pending_export' => (clone $orders)->where('delivery_status', 'picking_up')->count(),
            'delivering' => (clone $orders)->where('delivery_status', 'delivering')->count(),
            'stock_issues' => WarehouseInventory::query()->where('stock_quantity', '<', 10)->count(),
        ];
    }

    /** @return array<string,int> */
    private function rawAccountingBuckets(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        return [
            'pending_cod' => (clone $orders)->where('delivery_status', 'delivered')->count(),
            'paid' => (clone $orders)->where('delivery_status', 'paid')->count(),
            'cod_mismatch' => \App\Models\ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
            'reconciliation_pending' => (clone $orders)->where('reconciliation_status', 'pending')->count(),
        ];
    }

    /** @return Collection<int,CarbonInterface> */
    private function days(ReportFilterData $filter): Collection
    {
        $from = $filter->dateFrom?->copy()->startOfDay() ?? today();
        $to = $filter->dateTo?->copy()->startOfDay() ?? today();
        $days = max(1, min(366, $from->diffInDays($to) + 1));
        return collect(range(0, $days - 1))->map(fn (int $offset) => $from->copy()->addDays($offset));
    }

    /** @param array<string,int> $values */
    private function seriesFromMap(ReportFilterData $filter, array $values): array
    {
        return $this->days($filter)->map(fn (CarbonInterface $day) => [
            'label' => $day->format('d/m'),
            'value' => (int) ($values[$day->toDateString()] ?? 0),
        ])->values()->all();
    }

    private function dayKeyFromLabel(string $label, ReportFilterData $filter): string
    {
        $from = $filter->dateFrom?->copy()->startOfDay() ?? today();
        [$day, $month] = array_map('intval', explode('/', $label));
        $candidate = $from->copy()->setDate($from->year, $month, $day);
        if ($candidate->lt($from) && $from->month === 12 && $month === 1) {
            $candidate->addYear();
        }
        return $candidate->toDateString();
    }

    private function percentage(int $value, int $total): float
    {
        return $total === 0 ? 0.0 : round(($value / $total) * 100, 1);
    }
}
