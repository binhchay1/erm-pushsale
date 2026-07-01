<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\LeadIngestionRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ShippingWebhookEventRepository;
use App\Support\OrderRevenue;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BusinessOverviewController extends Controller
{
    public function __invoke(
        OrderRepository $orderStats,
        LeadIngestionRepository $leads,
        ShippingWebhookEventRepository $shippingEvents,
    ): Response {
        $days = collect(range(6, 0))->map(function (int $offset) {
            return Carbon::today()->subDays($offset);
        });

        $ordersByDay = $days->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => $orderStats->countOnDay($day),
        ])->values();

        $revenueByDay = $days->map(fn (Carbon $day) => [
            'label' => $day->format('d/m'),
            'value' => $orderStats->revenueOnDay($day),
        ])->values();

        $leadSources = $leads->todaySourceBreakdown();

        $revenueBreakdown = OrderRevenue::aggregate(
            \App\Models\Order::query()->whereDate('updated_at', '>=', $days->first()),
        );

        return Inertia::render('Admin/Reports/BusinessOverview', [
            'summary' => [
                'orders_total' => $orderStats->total(),
                'orders_delivered' => $orderStats->deliveredTotal(),
                'leads_today' => $leads->countToday(),
                'shipping_mismatch' => $shippingEvents->codMismatchTotal(),
                'revenue' => $revenueBreakdown['net'],
                'revenue_breakdown' => $revenueBreakdown,
            ],
            'charts' => [
                'orders_by_day' => $ordersByDay,
                'revenue_by_day' => $revenueByDay,
                'lead_sources' => $leadSources,
            ],
        ]);
    }
}
