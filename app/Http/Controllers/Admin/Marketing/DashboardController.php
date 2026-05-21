<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\MarketingDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, MarketingDashboardService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Admin/Marketing/Dashboard', $this->reportPageProps($request, [
            'report' => $service->build($filter),
        ]));
    }
}
