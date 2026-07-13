<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Data\MarketingDashboardFilterData;
use App\Http\Controllers\Controller;
use App\Services\Reports\PushsaleMarketingDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PushsaleMarketingDashboardService $service): Response
    {
        $filter = MarketingDashboardFilterData::fromRequest($request, $request->user());
        $isMarketingWorkspace = $request->routeIs('marketing.*');
        $baseUrl = $isMarketingWorkspace ? '/marketing/workspace' : '/admin/marketing/dashboard';

        return Inertia::render('Admin/Marketing/Dashboard', [
            'report' => $service->build($filter),
            'filters' => $filter->toInertia(),
            'filterOptions' => $service->options($request->user()),
            'filterRouteUrl' => $baseUrl,
            'endpoints' => [
                'chart' => $baseUrl.'/chart',
                'dailyMetrics' => $baseUrl.'/daily-metrics',
                'export' => $baseUrl.'/export',
                'operationConfig' => $request->user()?->isAdmin() ? '/admin/sales/operation-categories' : null,
                'activityHistory' => $request->user()?->isAdmin()
                    ? '/admin/activity-logs'
                    : '/marketing/leads',
            ],
            'activeMenuCode' => '2.1',
        ]);
    }
}
