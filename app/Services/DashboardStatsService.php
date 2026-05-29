<?php

namespace App\Services;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Models\User;
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
    public static function salesSnapshot(): array
    {
        return [
            'leads_pending' => 12 + random_int(0, 4),
            'orders_today' => 5 + random_int(0, 3),
            'reminders' => random_int(0, 4),
            'calls_series' => self::randomSeries(7, 8, 28),
            'conversion_series' => self::randomSeries(7, 12, 35),
            'updated_at' => now()->toIso8601String(),
        ];
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

    /**
     * @return list<array{label: string, value: int|float}>
     */
    private static function randomSeries(int $days, int|float $min, int|float $max): array
    {
        $points = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $points[] = [
                'label' => now()->subDays($i)->format('d/m'),
                'value' => random_int((int) $min, (int) $max),
            ];
        }

        return $points;
    }
}
