<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\SalesPerformanceReportService;
use App\Support\ReportCsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceReportController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, SalesPerformanceReportService $service): Response|StreamedResponse
    {
        $filter = $this->reportFilters($request);
        $report = $service->build($filter, $request->user());

        if ($request->query('export') === 'csv') {
            return ReportCsvExporter::download(
                'bao-cao-hieu-suat-sale.csv',
                $report['rows'],
                $report['columns'],
            );
        }

        $filterOptions = app(FilterOptionsService::class);

        return Inertia::render('Admin/Sales/PerformanceReport', array_merge(
            $this->reportPageProps($request, [
                'report' => $report,
                'routeUrl' => '/sales/performance',
            ]),
            ['filterFields' => $filterOptions->detailReportFilterFields($request->user())],
        ));
    }
}
