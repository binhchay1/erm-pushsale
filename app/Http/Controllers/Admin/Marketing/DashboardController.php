<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Data\MarketingDashboardFilterData;
use App\Http\Controllers\Controller;
use App\Services\Reports\PushsaleMarketingDashboardService;
use App\Services\Reporting\ReportSnapshotStore;
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

        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'pushsale-marketing-dashboard',
            $request->user(),
            $filter->toInertia(),
            $filter->dateFrom,
            $filter->dateTo,
            $filter->dateType->value,
            fn () => $service->build($filter),
            $request->boolean('refresh'),
        );

        return Inertia::render('Admin/Marketing/Dashboard', [
            'report' => $snapshot['data'],
            'filters' => $filter->toInertia(),
            'filterOptions' => $service->options($request->user()),
            'filterRouteUrl' => $baseUrl,
            'endpoints' => [
                'chart' => $baseUrl.'/chart',
                'packets' => $baseUrl.'/packets',
                'dailyMetrics' => $baseUrl.'/daily-metrics',
                'export' => $baseUrl.'/export',
                'operationConfig' => $request->user()?->isAdmin() ? '/admin/sales/operation-categories' : null,
                'activityHistory' => $request->user()?->isAdmin()
                    ? '/admin/activity-logs'
                    : '/marketing/leads',
            ],
            'activeMenuCode' => '2.1',
            'cachedAt' => $snapshot['cachedAt'],
            'fromCache' => $snapshot['fromCache'],
            'snapshotStorage' => $snapshot['storage'],
        ]);
    }
}
