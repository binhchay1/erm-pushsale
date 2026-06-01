<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\RevenueReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RevenueReportController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, RevenueReportService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Admin/Sales/RevenueReport', $this->reportPageProps($request, [
            'report' => $service->forSales($filter, $request->user()),
        ]));
    }
}
