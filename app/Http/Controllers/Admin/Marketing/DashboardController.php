<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\MarketingDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(
        Request $request,
        MarketingDashboardService $service,
        FilterOptionsService $filterOptions,
    ): Response {
        $filter = $this->reportFilters($request);

        return Inertia::render('Admin/Marketing/Dashboard', array_merge(
            $this->reportPageProps($request, [
                'report' => $service->build($filter),
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
