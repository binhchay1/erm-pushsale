<?php

namespace App\Services;

use App\Enums\LeadIngestionStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardStatsService
{
    /**
     * @return array<string, mixed>
     */
    public static function adminSnapshot(): array
    {
        return [
            ...self::todaySummary(),
            'revenue_series' => self::revenueSeries(),
            'orders_series' => self::ordersSeries(),
            'lead_sources' => self::leadSources(),
            'funnel' => self::funnel(),
            'top_sales' => self::topSales(),
            'top_sources' => self::topSources(),
            'alerts' => self::alerts(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function salesSnapshot(User $user): array
    {
        $orders = Order::query()->where('sale_user_id', $user->id);
        $activeStages = collect(OperationStage::cases())
            ->reject(fn (OperationStage $s) => in_array($s, [OperationStage::Skipped, OperationStage::NoOperation], true))
            ->map(fn (OperationStage $s) => $s->value)
            ->all();

        $pipeline = (clone $orders)->whereNull('closed_at')->whereIn('operation_stage', $activeStages);

        return [
            'leads_pending' => (clone $pipeline)->count(),
            'orders_today' => (clone $orders)->whereDate('closed_at', today())->count(),
            'reminders' => (clone $orders)
                ->whereNull('closed_at')
                ->whereIn('operation_stage', [
                    OperationStage::Call3->value,
                    OperationStage::Call4->value,
                    OperationStage::Call5->value,
                    OperationStage::Call6->value,
                    OperationStage::Care1->value,
                    OperationStage::Care2->value,
                    OperationStage::Care3->value,
                ])
                ->count(),
            'calls_series' => self::dailyOrderSeries($orders, 'contact_count', 7),
            'conversion_series' => self::dailyConversionSeries($orders, 7),
            'pipeline' => self::stageBreakdown($orders),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function marketingSnapshot(User $user): array
    {
        $sourceIds = MarketingSource::query()
            ->where('marketer_user_id', $user->id)
            ->pluck('id');

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
            'top_sources' => self::marketingTopSources($user),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function warehouseSnapshot(): array
    {
        return [
            'waiting_waybill' => Order::query()->where('delivery_status', 'waiting_waybill')->count(),
            'delivering' => Order::query()->where('delivery_status', 'delivering')->count(),
            'low_stock_items' => WarehouseInventory::query()->where('stock_quantity', '<', 10)->count(),
            'pending_export' => Order::query()->where('delivery_status', 'picking_up')->count(),
            'orders_series' => self::dailyOrderSeries(Order::query(), 'id', 7),
            'inventory_alerts' => WarehouseInventory::query()
                ->with(['product:id,name', 'warehouse:id,name'])
                ->where('stock_quantity', '<', 10)
                ->limit(5)
                ->get()
                ->map(fn (WarehouseInventory $row) => [
                    'product' => $row->product?->name ?? '—',
                    'warehouse' => $row->warehouse?->name ?? '—',
                    'stock' => $row->stock_quantity,
                ])
                ->all(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function accountingSnapshot(): array
    {
        return [
            'pending_cod' => Order::query()->where('delivery_status', 'delivered')->count(),
            'paid_today' => Order::query()->where('delivery_status', 'paid')->whereDate('updated_at', today())->count(),
            'cod_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
            'reconciliation_pending' => Order::query()->where('reconciliation_status', 'pending')->count(),
            'revenue_series' => self::revenueSeries(),
            'cod_series' => self::dailyOrderSeries(
                Order::query()->whereIn('delivery_status', ['delivered', 'paid']),
                'total',
                7,
            ),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function allocatorSnapshot(): array
    {
        return [
            'leads_today' => LeadIngestion::query()->whereDate('created_at', today())->count(),
            'pending_routing' => LeadIngestion::query()->where('status', LeadIngestionStatus::Pending->value)->count(),
            'processed_today' => LeadIngestion::query()
                ->where('status', LeadIngestionStatus::Processed->value)
                ->whereDate('updated_at', today())
                ->count(),
            'failed_leads' => LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count(),
            'duplicate_leads' => LeadIngestion::query()->where('status', LeadIngestionStatus::Duplicate->value)->count(),
            'lead_series' => self::dailyLeadSeries(7),
            'platform_breakdown' => LeadIngestion::query()
                ->selectRaw('platform as name, count(*) as value')
                ->groupBy('platform')
                ->orderByDesc('value')
                ->limit(5)
                ->get()
                ->map(fn (LeadIngestion $row) => ['name' => $row->name ?: 'Khác', 'value' => (int) $row->value])
                ->values()
                ->all(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private static function stageBreakdown(Builder $orders): array
    {
        return collect(OperationStage::cases())->map(function (OperationStage $stage) use ($orders) {
            return [
                'label' => $stage->label(),
                'value' => (clone $orders)->where('operation_stage', $stage->value)->whereNull('closed_at')->count(),
            ];
        })->filter(fn (array $row) => $row['value'] > 0)->values()->all();
    }

    /**
     * @return list<array{name: string, contacts: int, orders: int}>
     */
    private static function marketingTopSources(User $user): array
    {
        return MarketingSource::query()
            ->where('marketer_user_id', $user->id)
            ->withCount(['orders'])
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get()
            ->map(fn (MarketingSource $source) => [
                'name' => $source->name,
                'contacts' => (int) $source->contacts,
                'orders' => (int) $source->orders_count,
            ])
            ->all();
    }

    /**
     * @param  Builder<Order>  $query
     * @return list<array{label: string, value: int|float}>
     */
    private static function dailyOrderSeries(Builder $query, string $sumColumn, int $days): array
    {
        return self::days($days)->map(function (Carbon $day) use ($query, $sumColumn) {
            $dayQuery = (clone $query)->whereDate('data_arrived_at', $day);

            return [
                'label' => $day->format('d/m'),
                'value' => $sumColumn === 'id'
                    ? $dayQuery->count()
                    : (int) $dayQuery->sum($sumColumn),
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<Order>  $query
     * @return list<array{label: string, value: int|float}>
     */
    private static function dailyConversionSeries(Builder $query, int $days): array
    {
        return self::days($days)->map(function (Carbon $day) use ($query) {
            $total = (clone $query)->whereDate('data_arrived_at', $day)->count();
            $closed = (clone $query)->whereDate('data_arrived_at', $day)->whereNotNull('closed_at')->count();

            return [
                'label' => $day->format('d/m'),
                'value' => self::percentage($closed, $total),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private static function dailyLeadSeries(int $days): array
    {
        return self::days($days)->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => LeadIngestion::query()->whereDate('created_at', $day)->count(),
        ])->values()->all();
    }

    /**
     * @return array<string, int|float>
     */
    private static function todaySummary(): array
    {
        $todayOrders = Order::query()->whereDate('created_at', today());
        $deliveredTotal = Order::query()->whereIn('delivery_status', ['delivered', 'paid'])->count();
        $ordersTotal = Order::query()->count();

        return [
            'revenue_today' => (int) (clone $todayOrders)
                ->whereIn('delivery_status', ['delivered', 'paid'])
                ->sum('total'),
            'orders_closed' => (int) (clone $todayOrders)->count(),
            'leads_today' => LeadIngestion::query()->whereDate('created_at', today())->count(),
            'delivery_rate' => self::percentage($deliveredTotal, $ordersTotal),
            'failed_orders' => Order::query()
                ->whereIn('delivery_status', ['failed', 'cancelled', 'returned'])
                ->count(),
            'shipping_mismatch' => ShippingWebhookEvent::query()
                ->where('is_cod_mismatch', true)
                ->count(),
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private static function revenueSeries(int $days = 7): array
    {
        return self::days($days)->map(function (Carbon $day) {
            return [
                'label' => $day->format('d/m'),
                'value' => (int) Order::query()
                    ->whereDate('created_at', $day)
                    ->whereIn('delivery_status', ['delivered', 'paid'])
                    ->sum('total'),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private static function ordersSeries(int $days = 7): array
    {
        return self::days($days)->map(function (Carbon $day) {
            return [
                'label' => $day->format('d/m'),
                'value' => Order::query()->whereDate('created_at', $day)->count(),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{name: string, value: int}>
     */
    private static function leadSources(): array
    {
        return LeadIngestion::query()
            ->whereDate('created_at', today())
            ->selectRaw('platform as name, count(*) as value')
            ->groupBy('platform')
            ->orderByDesc('value')
            ->limit(4)
            ->get()
            ->map(fn (LeadIngestion $lead) => [
                'name' => $lead->name ?: 'Khác',
                'value' => (int) $lead->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private static function funnel(): array
    {
        $leads = LeadIngestion::query()->count();
        $orders = Order::query()->count();
        $closed = Order::query()->whereNotNull('closed_at')->count();
        $delivered = Order::query()->whereIn('delivery_status', ['delivered', 'paid'])->count();
        $paid = Order::query()->where('delivery_status', 'paid')->count();

        return [
            ['label' => 'Lead', 'value' => $leads],
            ['label' => 'Đơn', 'value' => $orders],
            ['label' => 'Chốt', 'value' => $closed],
            ['label' => 'Giao', 'value' => $delivered],
            ['label' => 'Paid', 'value' => $paid],
        ];
    }

    /**
     * @return list<array{name: string, orders: int, revenue: int, conversion_rate: float}>
     */
    private static function topSales(): array
    {
        return User::query()
            ->where('role', UserRole::Sales->value)
            ->leftJoin('orders', 'orders.sale_user_id', '=', 'users.id')
            ->select('users.id', 'users.name')
            ->selectRaw('count(orders.id) as orders_count')
            ->selectRaw("sum(case when orders.delivery_status in ('delivered', 'paid') then orders.total else 0 end) as revenue")
            ->selectRaw("sum(case when orders.closed_at is not null then 1 else 0 end) as closed_count")
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'name' => $user->name,
                'orders' => (int) $user->orders_count,
                'revenue' => (int) $user->revenue,
                'conversion_rate' => self::percentage((int) $user->closed_count, (int) $user->orders_count),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, leads: int, orders: int, revenue: int}>
     */
    private static function topSources(): array
    {
        $leadCounts = LeadIngestion::query()
            ->selectRaw('platform, count(*) as leads_count')
            ->groupBy('platform')
            ->pluck('leads_count', 'platform');

        $sourceRows = Order::query()
            ->leftJoin('marketing_sources', 'marketing_sources.id', '=', 'orders.marketing_source_id')
            ->selectRaw("coalesce(marketing_sources.utm_source, marketing_sources.ad_channel, marketing_sources.name, 'Khác') as source_name")
            ->selectRaw('count(orders.id) as orders_count')
            ->selectRaw("sum(case when orders.delivery_status in ('delivered', 'paid') then orders.total else 0 end) as revenue")
            ->groupBy('source_name')
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        return $sourceRows->map(fn (object $row) => [
            'name' => $row->source_name,
            'leads' => (int) ($leadCounts[$row->source_name] ?? 0),
            'orders' => (int) $row->orders_count,
            'revenue' => (int) $row->revenue,
        ])->values()->all();
    }

    /**
     * @return list<array{type: string, title: string, value: int, description: string}>
     */
    private static function alerts(): array
    {
        $failedOrders = Order::query()->whereIn('delivery_status', ['failed', 'cancelled', 'returned'])->count();
        $codMismatch = ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count();
        $failedLeads = LeadIngestion::query()->where('status', LeadIngestionStatus::Failed->value)->count();
        $pendingOrders = Order::query()->where('delivery_status', 'waiting_waybill')->count();

        return collect([
            [
                'type' => 'danger',
                'title' => 'Đơn lỗi / hoàn hủy',
                'value' => $failedOrders,
                'description' => 'Cần rà soát trạng thái giao hàng.',
            ],
            [
                'type' => 'warning',
                'title' => 'Lệch COD',
                'value' => $codMismatch,
                'description' => 'Webhook vận chuyển có số tiền lệch.',
            ],
            [
                'type' => 'warning',
                'title' => 'Lead lỗi',
                'value' => $failedLeads,
                'description' => 'Lead ingest thất bại cần retry.',
            ],
            [
                'type' => 'info',
                'title' => 'Chờ vận đơn',
                'value' => $pendingOrders,
                'description' => 'Đơn đang chờ tạo/đẩy vận đơn.',
            ],
        ])->filter(fn (array $alert) => $alert['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Carbon>
     */
    private static function days(int $days)
    {
        return collect(range($days - 1, 0))->map(fn (int $offset) => Carbon::today()->subDays($offset));
    }

    private static function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }
}
