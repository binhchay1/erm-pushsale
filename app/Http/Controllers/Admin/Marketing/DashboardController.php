<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\MarketingDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __invoke(
        Request $request,
        MarketingDashboardService $service,
        FilterOptionsService $filterOptions,
    ): Response {
        $filter = $this->reportFilters($request);
        $cached = $this->maybeCachedReport(
            $request,
            'marketing-dashboard',
            $filter,
            fn () => $service->build($filter),
        );

        return Inertia::render('Admin/Marketing/Dashboard', array_merge(
            $this->reportPageProps($request, [
                'report' => $cached['data'],
                'cachedAt' => $cached['cachedAt'],
                'fromCache' => $cached['fromCache'],
            ]),
            [
                'filterFields' => $filterOptions->marketingDashboardFilterFields($request->user()),
                'filterRouteUrl' => $request->routeIs('marketing.*')
                    ? '/marketing/workspace'
                    : '/admin/marketing/dashboard',
            ]
        ));
    }
}
