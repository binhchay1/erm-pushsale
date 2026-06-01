<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\CeoReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CeoReportController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, CeoReportService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Admin/Reports/CeoReport', $this->reportPageProps($request, [
            'report' => $service->build($filter, $request->user()),
        ]));
    }
}
