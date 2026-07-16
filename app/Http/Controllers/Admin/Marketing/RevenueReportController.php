<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\RevenueReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RevenueReportController extends Controller
{
    use InteractsWithReportFilters, InteractsWithReportSnapshots;

    public function __invoke(Request $request, RevenueReportService $service): Response
    {
        $filter = $this->reportFilters($request);
        $snapshot = $this->maybeCachedReport(
            $request,
            'marketing-revenue',
            $filter,
            fn () => $service->forMarketers($filter, $request->user()),
        );

        return Inertia::render('Admin/Marketing/RevenueReport', array_merge(
            $this->reportPageProps($request, [
                'report' => $snapshot['data'],
                'cachedAt' => $snapshot['cachedAt'],
                'fromCache' => $snapshot['fromCache'],
            ]),
            [
                'filterFields' => app(FilterOptionsService::class)->revenueReportFilterFields($request->user(), 'marketing'),
                'routeUrl' => $request->routeIs('marketing.*') ? '/marketing/revenue' : '/admin/marketing/revenue',
            ],
        ));
    }
}
