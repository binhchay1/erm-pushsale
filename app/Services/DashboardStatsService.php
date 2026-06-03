<?php

namespace App\Services;

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
use App\Services\Reports\ReportMetricService;
use App\Services\Reports\ReportQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardStatsService
{
    public function __construct(
        private readonly ReportMetricService $metrics,
    ) {}

    /** @return array<string, mixed> */
    public static function adminSnapshot(?User $user = null, ?ReportFilterData $filter = null): array
    {
        if ($user && $filter) {
            return app(self::class)->dashboardSnapshot($user, $filter, 'admin');
        }

        return [
            ...self::todaySummary(),
            'revenue_series' => self::revenueSeries(),
            'orders_series' => self::ordersSeries(),
            'lead_series' => self::dailyLeadSeries(7),
            'lead_sources' => self::leadSources(),
            'funnel' => self::funnel(),
            'top_sales' => self::topSales(),
            'top_sources' => self::topSources(),
            'alerts' => self::alerts(),
            'inventory_alerts' => self::inventoryAlerts(),
            'reconciliation_pending' => Order::query()->where('reconciliation_status', 'pending')->count(),
            'cod_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
            'failed_leads' => LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count(),
            'carrier_performance' => app(ReportMetricService::class)->carrierPerformance(
                $user ?? auth()->user() ?? new User,
                $filter ?? ReportFilterData::fromRequest(request())
            ),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function salesSnapshot(User $user, ?ReportFilterData $filter = null): array
    {
        if ($filter) {
            return app(self::class)->dashboardSnapshot($user, $filter, 'sales');
        }

        $orders = Order::query()->where('sale_user_id', $user->id);

        return [
            'leads_pending' => self::activePipeline($orders)->count(),
            'orders_today' => (clone $orders)->whereDate('closed_at', today())->count(),
            'reminders' => self::reminderOrders($orders)->count(),
            'calls_series' => self::dailyOrderSeries($orders, 'contact_count', 7),
            'conversion_series' => self::dailyConversionSeries($orders, 7),
            'orders_closed_series' => self::dailyClosedOrderSeries($orders, 7),
            'pipeline' => self::stageBreakdown($orders),
            'funnel' => self::salesFunnel($orders),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function marketingSnapshot(User $user, ?ReportFilterData $filter = null): array
    {
        if ($filter) {
            return app(self::class)->dashboardSnapshot($user, $filter, 'marketing');
        }

        $sourceIds = MarketingSource::query()->where('marketer_user_id', $user->id)->pluck('id');
        $orders = Order::query()->when(
            $sourceIds->isNotEmpty(),
            fn (Builder $q) => $q->whereIn('marketing_source_id', $sourceIds),
            fn (Builder $q) => $q->where('marketer_user_id', $user->id),
        );
        $sources = MarketingSource::query()->where('marketer_user_id', $user->id);

        return [
            'active_campaigns' => (clone $sources)->where('is_active', true)->count(),
            'leads_today' => LeadIngestion::query()->whereDate('created_at', today())->count(),
            'contacts_today' => (int) (clone $sources)->sum('contacts'),
            'orders_closed' => (clone $orders)->whereNotNull('closed_at')->whereDate('closed_at', today())->count(),
            'budget_total' => (int) (clone $sources)->sum('budget'),
            'lead_series' => self::dailyLeadSeries(7),
            'conversion_series' => self::dailyConversionSeries($orders, 7),
            'lead_sources' => self::marketerLeadSources($user),
            'revenue_series' => self::marketerRevenueSeries($orders, 7),
            'funnel' => self::marketerFunnel($user, $orders),
            'top_sources' => self::marketingTopSources($user),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function warehouseSnapshot(?User $user = null, ?ReportFilterData $filter = null): array
    {
        if ($user && $filter) {
            return app(self::class)->dashboardSnapshot($user, $filter, 'warehouse');
        }

        return [
            'waiting_waybill' => Order::query()->where('delivery_status', 'waiting_waybill')->count(),
            'delivering' => Order::query()->where('delivery_status', 'delivering')->count(),
            'low_stock_items' => WarehouseInventory::query()->where('stock_quantity', '<', 10)->count(),
            'pending_export' => Order::query()->where('delivery_status', 'picking_up')->count(),
            'orders_series' => self::dailyOrderSeries(Order::query(), 'id', 7),
            'delivery_breakdown' => self::deliveryBreakdown(
                Order::query()->where('delivery_status', 'waiting_waybill')->count(),
                Order::query()->where('delivery_status', 'picking_up')->count(),
                Order::query()->where('delivery_status', 'delivering')->count(),
            ),
            'inventory_alerts' => self::inventoryAlerts(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function accountingSnapshot(?User $user = null, ?ReportFilterData $filter = null): array
    {
        if ($user && $filter) {
            return app(self::class)->dashboardSnapshot($user, $filter, 'accounting');
        }

        return [
            'pending_cod' => Order::query()->where('delivery_status', 'delivered')->count(),
            'paid_today' => Order::query()->where('delivery_status', 'paid')->whereDate('updated_at', today())->count(),
            'cod_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
            'reconciliation_pending' => Order::query()->where('reconciliation_status', 'pending')->count(),
            'revenue_series' => self::revenueSeries(),
            'cod_series' => self::dailyOrderSeries(Order::query()->whereIn('delivery_status', DeliveryStatus::revenueEligible()), 'total', 7),
            'paid_orders_series' => self::dailyPaidOrderSeries(7),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function allocatorSnapshot(?User $user = null, ?ReportFilterData $filter = null): array
    {
        if ($user && $filter) {
            return app(self::class)->dashboardSnapshot($user, $filter, 'allocator');
        }

        return [
            'leads_today' => LeadIngestion::query()->whereDate('created_at', today())->count(),
            'pending_routing' => LeadIngestion::query()->where('status', LeadIngestionStatus::Pending->value)->count(),
            'processed_today' => LeadIngestion::query()->where('status', LeadIngestionStatus::Processed->value)->whereDate('updated_at', today())->count(),
            'failed_leads' => LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count(),
            'duplicate_leads' => LeadIngestion::query()->where('status', LeadIngestionStatus::Duplicate->value)->count(),
            'lead_series' => self::dailyLeadSeries(7),
            'processed_series' => self::dailyProcessedLeadSeries(7),
            'platform_breakdown' => self::platformBreakdown(),
            'routing_status_breakdown' => self::routingStatusBreakdown(
                LeadIngestion::query()->where('status', LeadIngestionStatus::Pending->value)->count(),
                LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count(),
                LeadIngestion::query()->where('status', LeadIngestionStatus::Duplicate->value)->count(),
            ),
            'funnel' => self::allocatorFunnel(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function snapshotFor(User $user, ReportFilterData $filter): array
    {
        return match ($user->role) {
            UserRole::Admin => self::adminSnapshot($user, $filter),
            UserRole::Sales => self::salesSnapshot($user, $filter),
            UserRole::Marketing => self::marketingSnapshot($user, $filter),
            UserRole::Warehouse => self::warehouseSnapshot($user, $filter),
            UserRole::Accounting => self::accountingSnapshot($user, $filter),
            UserRole::Allocator => self::allocatorSnapshot($user, $filter),
        };
    }

    /** @return array<string, mixed> */
    public function dashboardSnapshot(User $user, ReportFilterData $filter, string $role): array
    {
        $summary = $this->metrics->kpiSummary($user, $filter);
        $legacyToday = self::todaySummary();
        $base = [
            'summary' => $summary,
            'revenue_today' => $legacyToday['revenue_today'],
            'orders_closed' => $legacyToday['orders_closed'],
            'leads_today' => $summary['leads'],
            'delivery_rate' => $legacyToday['delivery_rate'],
            'failed_orders' => $legacyToday['failed_orders'],
            'shipping_mismatch' => $legacyToday['shipping_mismatch'],
            'revenue_series' => $this->metrics->revenueSeries($user, $filter),
            'orders_series' => $this->metrics->orderSeries($user, $filter),
            'lead_series' => $this->metrics->leadSeries($user, $filter),
            'lead_sources' => $this->metrics->leadSourceBreakdown($user, $filter),
            'funnel' => $this->metrics->funnel($user, $filter),
            'updated_at' => now()->toIso8601String(),
        ];

        return match ($role) {
            'admin' => array_merge($base, [
                'top_sales' => $this->metrics->topSales($user, $filter),
                'top_sources' => $this->metrics->topSources($user, $filter),
                'alerts' => self::alerts(),
                'inventory_alerts' => self::inventoryAlerts(),
                'reconciliation_pending' => Order::query()->where('reconciliation_status', 'pending')->count(),
                'cod_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
                'failed_leads' => LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count(),
                'carrier_performance' => $this->metrics->carrierPerformance($user, $filter),
            ]),
            'sales' => array_merge($base, [
                'leads_pending' => $summary['orders'],
                'orders_today' => $summary['closed_orders'],
                'reminders' => $summary['orders'] - $summary['closed_orders'],
                'calls_series' => $this->metrics->orderSeries($user, $filter, 'contact_count'),
                'conversion_series' => $this->metrics->orderSeries($user, $filter),
                'orders_closed_series' => self::dailyClosedOrderSeries(
                    app(ReportQueryService::class)->orders($user, $filter),
                    7,
                ),
                'pipeline' => $this->metrics->stageBreakdown($user, $filter),
            ]),
            'marketing' => array_merge($base, [
                'active_campaigns' => MarketingSource::query()->where('marketer_user_id', $user->id)->where('is_active', true)->count(),
                'leads_today' => $summary['leads'],
                'orders_closed' => $summary['closed_orders'],
                'budget_total' => (int) MarketingSource::query()->where('marketer_user_id', $user->id)->sum('budget'),
                'top_sources' => $this->metrics->topSources($user, $filter),
            ]),
            'warehouse' => array_merge($base, $warehouseBuckets = $this->metrics->warehouseBuckets($user, $filter), [
                'inventory_alerts' => self::inventoryAlerts(),
                'delivery_breakdown' => self::deliveryBreakdown(
                    $warehouseBuckets['waiting_waybill'] ?? 0,
                    $warehouseBuckets['pending_export'] ?? 0,
                    $warehouseBuckets['delivering'] ?? 0,
                ),
            ]),
            'accounting' => array_merge($base, $this->metrics->accountingBuckets($user, $filter), [
                'paid_orders_series' => self::dailyPaidOrderSeries(7),
            ]),
            'allocator' => array_merge($base, [
                'leads_today' => $summary['leads'],
                'pending_routing' => $summary['leads'] - $summary['processed_leads'],
                'processed_today' => $summary['processed_leads'],
                'failed_leads' => $summary['failed_leads'],
                'duplicate_leads' => $summary['duplicate_leads'],
                'platform_breakdown' => $this->metrics->leadSourceBreakdown($user, $filter),
                'processed_series' => self::dailyProcessedLeadSeries(7),
                'routing_status_breakdown' => self::routingStatusBreakdown(
                    $summary['leads'] - $summary['processed_leads'],
                    $summary['failed_leads'],
                    $summary['duplicate_leads'],
                ),
                'funnel' => self::allocatorFunnel(),
            ]),
            default => $base,
        };
    }

    /** @return Builder<Order> */
    private static function activePipeline(Builder $orders): Builder
    {
        $activeStages = collect(OperationStage::cases())
            ->reject(fn (OperationStage $s) => in_array($s, [OperationStage::Skipped, OperationStage::NoOperation], true))
            ->map(fn (OperationStage $s) => $s->value)
            ->all();

        return (clone $orders)->whereNull('closed_at')->whereIn('operation_stage', $activeStages);
    }

    /** @return Builder<Order> */
    private static function reminderOrders(Builder $orders): Builder
    {
        return (clone $orders)->whereNull('closed_at')->whereIn('operation_stage', [
            OperationStage::Call3->value,
            OperationStage::Call4->value,
            OperationStage::Call5->value,
            OperationStage::Call6->value,
            OperationStage::Care1->value,
            OperationStage::Care2->value,
            OperationStage::Care3->value,
        ]);
    }

    /** @return list<array{label: string, value: int}> */
    private static function stageBreakdown(Builder $orders): array
    {
        return collect(OperationStage::cases())->map(fn (OperationStage $stage) => [
            'label' => $stage->label(),
            'value' => (clone $orders)->where('operation_stage', $stage->value)->whereNull('closed_at')->count(),
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /** @return list<array{name: string, contacts: int, orders: int}> */
    private static function marketingTopSources(User $user): array
    {
        return MarketingSource::query()->where('marketer_user_id', $user->id)->withCount(['orders'])->orderByDesc('orders_count')->limit(5)->get()->map(fn (MarketingSource $source) => [
            'name' => $source->name,
            'contacts' => (int) $source->contacts,
            'orders' => (int) $source->orders_count,
        ])->all();
    }

    /** @param Builder<Order> $query
     * @return list<array{label: string, value: int|float}>
     */
    private static function dailyOrderSeries(Builder $query, string $sumColumn, int $days): array
    {
        return self::days($days)->map(function (Carbon $day) use ($query, $sumColumn) {
            $dayQuery = (clone $query)->whereDate('data_arrived_at', $day);

            return ['label' => $day->format('d/m'), 'value' => $sumColumn === 'id' ? $dayQuery->count() : (int) $dayQuery->sum($sumColumn)];
        })->values()->all();
    }

    /** @param Builder<Order> $query
     * @return list<array{label: string, value: int|float}>
     */
    private static function dailyConversionSeries(Builder $query, int $days): array
    {
        return self::days($days)->map(function (Carbon $day) use ($query) {
            $total = (clone $query)->whereDate('data_arrived_at', $day)->count();
            $closed = (clone $query)->whereDate('data_arrived_at', $day)->whereNotNull('closed_at')->count();

            return ['label' => $day->format('d/m'), 'value' => self::percentage($closed, $total)];
        })->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function dailyLeadSeries(int $days): array
    {
        return self::days($days)->map(fn (Carbon $day) => ['label' => $day->format('d/m'), 'value' => LeadIngestion::query()->whereDate('created_at', $day)->count()])->values()->all();
    }

    /** @param Builder<Order> $query
     * @return list<array{label: string, value: int}>
     */
    private static function dailyClosedOrderSeries(Builder $query, int $days): array
    {
        return self::days($days)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => (clone $query)->whereDate('closed_at', $day)->count(),
        ])->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function dailyPaidOrderSeries(int $days): array
    {
        return self::days($days)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => Order::query()->where('delivery_status', 'paid')->whereDate('updated_at', $day)->count(),
        ])->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function dailyProcessedLeadSeries(int $days): array
    {
        return self::days($days)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => LeadIngestion::query()->where('status', LeadIngestionStatus::Processed->value)->whereDate('updated_at', $day)->count(),
        ])->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function deliveryBreakdown(int $waitingWaybill, int $pendingExport, int $delivering): array
    {
        return collect([
            ['label' => 'Chờ vận đơn', 'value' => $waitingWaybill],
            ['label' => 'Chờ lấy hàng', 'value' => $pendingExport],
            ['label' => 'Đang giao', 'value' => $delivering],
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function routingStatusBreakdown(int $pending, int $failed, int $duplicate): array
    {
        return collect([
            ['label' => 'Chờ phân số', 'value' => $pending],
            ['label' => 'Lỗi', 'value' => $failed],
            ['label' => 'Trùng', 'value' => $duplicate],
        ])->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /** @return list<array{name: string, value: int}> */
    private static function marketerLeadSources(User $user): array
    {
        $sourceIds = MarketingSource::query()->where('marketer_user_id', $user->id)->pluck('id');
        $query = LeadIngestion::query()->whereDate('created_at', today());

        if ($sourceIds->isNotEmpty()) {
            $query->whereIn('marketing_source_id', $sourceIds);
        }

        return $query->selectRaw('platform as name, count(*) as value')
            ->groupBy('platform')
            ->orderByDesc('value')
            ->limit(4)
            ->get()
            ->map(fn (LeadIngestion $row) => ['name' => $row->name ?: 'Khác', 'value' => (int) $row->value])
            ->values()
            ->all();
    }

    /** @param Builder<Order> $orders
     * @return list<array{label: string, value: int}>
     */
    private static function marketerRevenueSeries(Builder $orders, int $days): array
    {
        $paidOrders = (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible());

        return self::days($days)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => (int) (clone $paidOrders)->whereDate('created_at', $day)->sum('total'),
        ])->values()->all();
    }

    /** @param Builder<Order> $orders
     * @return list<array{label: string, value: int}>
     */
    private static function marketerFunnel(User $user, Builder $orders): array
    {
        $sourceIds = MarketingSource::query()->where('marketer_user_id', $user->id)->pluck('id');
        $leadQuery = LeadIngestion::query();

        if ($sourceIds->isNotEmpty()) {
            $leadQuery->whereIn('marketing_source_id', $sourceIds);
        }

        return [
            ['label' => 'Lead', 'value' => $leadQuery->count()],
            ['label' => 'Đơn', 'value' => (clone $orders)->count()],
            ['label' => 'Chốt', 'value' => (clone $orders)->whereNotNull('closed_at')->count()],
            ['label' => 'Giao', 'value' => (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count()],
        ];
    }

    /** @param Builder<Order> $orders
     * @return list<array{label: string, value: int}>
     */
    private static function salesFunnel(Builder $orders): array
    {
        return [
            ['label' => 'Lead', 'value' => (clone $orders)->count()],
            ['label' => 'Đang xử lý', 'value' => self::activePipeline($orders)->count()],
            ['label' => 'Chốt', 'value' => (clone $orders)->whereNotNull('closed_at')->count()],
            ['label' => 'Giao', 'value' => (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count()],
        ];
    }

    /** @return list<array{label: string, value: int}> */
    private static function allocatorFunnel(): array
    {
        return [
            ['label' => 'Lead ingest', 'value' => LeadIngestion::query()->count()],
            ['label' => 'Chờ phân số', 'value' => LeadIngestion::query()->where('status', LeadIngestionStatus::Pending->value)->count()],
            ['label' => 'Đã xử lý', 'value' => LeadIngestion::query()->where('status', LeadIngestionStatus::Processed->value)->count()],
            ['label' => 'Lead lỗi', 'value' => LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count()],
        ];
    }

    /** @return array<string, int|float> */
    private static function todaySummary(): array
    {
        $todayOrders = Order::query()->whereDate('created_at', today());
        $deliveredTotal = Order::query()->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count();
        $ordersTotal = Order::query()->count();

        return [
            'revenue_today' => (int) (clone $todayOrders)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->sum('total'),
            'orders_closed' => (int) (clone $todayOrders)->count(),
            'leads_today' => LeadIngestion::query()->whereDate('created_at', today())->count(),
            'delivery_rate' => self::percentage($deliveredTotal, $ordersTotal),
            'failed_orders' => Order::query()->whereIn('delivery_status', ['failed', 'cancelled', 'returned'])->count(),
            'shipping_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
        ];
    }

    /** @return list<array{label: string, value: int}> */
    private static function revenueSeries(int $days = 7): array
    {
        return self::days($days)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => (int) Order::query()->whereDate('created_at', $day)->whereIn('delivery_status', DeliveryStatus::revenueEligible())->sum('total'),
        ])->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function ordersSeries(int $days = 7): array
    {
        return self::days($days)->map(fn (Carbon $day) => ['label' => $day->format('d/m'), 'value' => Order::query()->whereDate('created_at', $day)->count()])->values()->all();
    }

    /** @return list<array{name: string, value: int}> */
    private static function leadSources(): array
    {
        return LeadIngestion::query()->whereDate('created_at', today())->selectRaw('platform as name, count(*) as value')->groupBy('platform')->orderByDesc('value')->limit(4)->get()->map(fn (LeadIngestion $lead) => [
            'name' => $lead->name ?: 'Khác',
            'value' => (int) $lead->value,
        ])->values()->all();
    }

    /** @return list<array{label: string, value: int}> */
    private static function funnel(): array
    {
        return [
            ['label' => 'Lead', 'value' => LeadIngestion::query()->count()],
            ['label' => 'Đơn', 'value' => Order::query()->count()],
            ['label' => 'Chốt', 'value' => Order::query()->whereNotNull('closed_at')->count()],
            ['label' => 'Giao', 'value' => Order::query()->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count()],
            ['label' => 'Paid', 'value' => Order::query()->where('delivery_status', 'paid')->count()],
        ];
    }

    /** @return list<array{name: string, orders: int, revenue: int, conversion_rate: float}> */
    private static function topSales(): array
    {
        return User::query()->where('role', UserRole::Sales->value)->leftJoin('orders', 'orders.sale_user_id', '=', 'users.id')->select('users.id', 'users.name')->selectRaw('count(orders.id) as orders_count')->selectRaw("sum(case when orders.delivery_status in ('delivered', 'paid') then orders.total else 0 end) as revenue")->selectRaw('sum(case when orders.closed_at is not null then 1 else 0 end) as closed_count')->groupBy('users.id', 'users.name')->orderByDesc('revenue')->orderByDesc('orders_count')->limit(5)->get()->map(fn (User $user) => [
            'name' => $user->name,
            'orders' => (int) $user->orders_count,
            'revenue' => (int) $user->revenue,
            'conversion_rate' => self::percentage((int) $user->closed_count, (int) $user->orders_count),
        ])->values()->all();
    }

    /** @return list<array{name: string, leads: int, orders: int, revenue: int}> */
    private static function topSources(): array
    {
        $leadCounts = LeadIngestion::query()->selectRaw('platform, count(*) as leads_count')->groupBy('platform')->pluck('leads_count', 'platform');
        $sourceRows = Order::query()->leftJoin('marketing_sources', 'marketing_sources.id', '=', 'orders.marketing_source_id')->selectRaw("coalesce(marketing_sources.utm_source, marketing_sources.ad_channel, marketing_sources.name, 'Khác') as source_name")->selectRaw('count(orders.id) as orders_count')->selectRaw("sum(case when orders.delivery_status in ('delivered', 'paid') then orders.total else 0 end) as revenue")->groupBy('source_name')->orderByDesc('revenue')->orderByDesc('orders_count')->limit(5)->get();

        return $sourceRows->map(fn (object $row) => ['name' => $row->source_name, 'leads' => (int) ($leadCounts[$row->source_name] ?? 0), 'orders' => (int) $row->orders_count, 'revenue' => (int) $row->revenue])->values()->all();
    }

    /** @return list<array{type: string, title: string, value: int, description: string}> */
    private static function alerts(): array
    {
        return collect([
            ['type' => 'danger', 'title' => 'Đơn lỗi / hoàn hủy', 'value' => Order::query()->whereIn('delivery_status', ['failed', 'cancelled', 'returned'])->count(), 'description' => 'Cần rà soát trạng thái giao hàng.'],
            ['type' => 'warning', 'title' => 'Lệch COD', 'value' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(), 'description' => 'Webhook vận chuyển có số tiền lệch.'],
            ['type' => 'warning', 'title' => 'Lead lỗi', 'value' => LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count(), 'description' => 'Lead ingest thất bại cần retry.'],
            ['type' => 'info', 'title' => 'Chờ vận đơn', 'value' => Order::query()->where('delivery_status', 'waiting_waybill')->count(), 'description' => 'Đơn đang chờ tạo/đẩy vận đơn.'],
        ])->filter(fn (array $alert) => $alert['value'] > 0)->values()->all();
    }

    /** @return list<array{product: string, warehouse: string, stock: int}> */
    private static function inventoryAlerts(): array
    {
        return WarehouseInventory::query()->with(['product:id,name', 'warehouse:id,name'])->where('stock_quantity', '<', 10)->limit(5)->get()->map(fn (WarehouseInventory $row) => [
            'product' => $row->product?->name ?? '—',
            'warehouse' => $row->warehouse?->name ?? '—',
            'stock' => $row->stock_quantity,
        ])->all();
    }

    /** @return list<array{name: string, value: int}> */
    private static function platformBreakdown(): array
    {
        return LeadIngestion::query()->selectRaw('platform as name, count(*) as value')->groupBy('platform')->orderByDesc('value')->limit(5)->get()->map(fn (LeadIngestion $row) => ['name' => $row->name ?: 'Khác', 'value' => (int) $row->value])->values()->all();
    }

    /** @return Collection<int, Carbon> */
    private static function days(int $days)
    {
        return collect(range($days - 1, 0))->map(fn (int $offset) => Carbon::today()->subDays($offset));
    }

    private static function percentage(int $value, int $total): float
    {
        return $total === 0 ? 0.0 : round(($value / $total) * 100, 1);
    }
}
