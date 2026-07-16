<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\SalesPerformanceReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceReportController extends Controller
{
    use ExportsReportData;
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __invoke(Request $request, SalesPerformanceReportService $service): Response|StreamedResponse|HttpResponse
    {
        $filter = $this->reportFilters($request);
        $snapshot = $this->maybeCachedReport(
            $request,
            'admin-sales-performance',
            $filter,
            fn () => $service->build($filter, $request->user()),
        );
        $report = $snapshot['data'];

        if ($exported = $this->maybeExportReport(
            $request,
            $report['rows'],
            $report['columns'],
            'bao-cao-hieu-suat-sale',
            [
                'title' => __('reports.performance.report_title'),
                'subtitle' => __('reports.performance.report_desc'),
                'date_from' => $filter->dateFrom?->toDateString(),
                'date_to' => $filter->dateTo?->toDateString(),
            ],
        )) {
            return $exported;
        }

        return Inertia::render('Admin/Sales/PerformanceReport', $this->pageProps($request, [
            'report' => $report,
            'routeUrl' => '/admin/sales/performance',
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
        ]));
    }

    /** @return array<string, mixed> */
    private function pageProps(Request $request, array $data): array
    {
        $filterOptions = app(FilterOptionsService::class);

        return array_merge($this->reportPageProps($request, $data), [
            'filterFields' => $filterOptions->detailReportFilterFields($request->user()),
        ]);
    }
}
