<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BusinessOverviewController extends Controller
{
    public function __invoke(): Response
    {
        $days = collect(range(6, 0))->map(function (int $offset) {
            return Carbon::today()->subDays($offset);
        });

        $ordersByDay = $days->map(function (Carbon $day) {
            return [
                'label' => $day->format('d/m'),
                'value' => Order::query()->whereDate('created_at', $day)->count(),
            ];
        })->values();

        $revenueByDay = $days->map(function (Carbon $day) {
            return [
                'label' => $day->format('d/m'),
                'value' => (int) Order::query()
                    ->whereDate('created_at', $day)
                    ->whereIn('delivery_status', ['delivered', 'paid'])
                    ->sum('total'),
            ];
        })->values();

        $leadSources = LeadIngestion::query()
            ->whereDate('created_at', today())
            ->selectRaw('platform as name, count(*) as value')
            ->groupBy('platform')
            ->orderByDesc('value')
            ->limit(4)
            ->get();

        return Inertia::render('Admin/Reports/BusinessOverview', [
            'summary' => [
                'orders_total' => Order::query()->count(),
                'orders_delivered' => Order::query()->whereIn('delivery_status', ['delivered', 'paid'])->count(),
                'leads_today' => LeadIngestion::query()->whereDate('created_at', today())->count(),
                'shipping_mismatch' => ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count(),
            ],
            'charts' => [
                'orders_by_day' => $ordersByDay,
                'revenue_by_day' => $revenueByDay,
                'lead_sources' => $leadSources,
            ],
        ]);
    }
}
