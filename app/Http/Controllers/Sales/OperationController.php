<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use App\Services\Operations\SaleOperationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, SaleOperationService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Sales/Workspace', $this->reportPageProps($request, [
            'stats' => DashboardStatsService::salesSnapshot(),
            'report' => $service->build($filter),
        ]));
    }
}
