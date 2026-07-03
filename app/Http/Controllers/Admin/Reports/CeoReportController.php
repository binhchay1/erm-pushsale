<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Reports\CeoReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CeoReportController extends Controller
{
    use ExportsReportData;
    use InteractsWithReportFilters;

    public function __invoke(Request $request, CeoReportService $service): Response|StreamedResponse|HttpResponse
    {
        $filter = $this->reportFilters($request);
        $report = $service->build($filter, $request->user());

        $columns = [
            ['key' => 'saleStaffName', 'label' => __('reports.ceo_report.sale')],
            ['key' => 'newContact', 'label' => __('reports.ceo_report.new_contact'), 'format' => 'number'],
            ['key' => 'newClosed', 'label' => __('reports.ceo_report.new_closed'), 'format' => 'number'],
            ['key' => 'newCloseRate', 'label' => __('reports.ceo_report.new_close_rate'), 'format' => 'percent'],
            ['key' => 'newProductQty', 'label' => __('reports.ceo_report.new_products'), 'format' => 'number'],
            ['key' => 'newEstRevenue', 'label' => __('reports.ceo_report.new_revenue'), 'format' => 'currency'],
            ['key' => 'oldContact', 'label' => __('reports.ceo_report.old_contact'), 'format' => 'number'],
            ['key' => 'oldClosed', 'label' => __('reports.ceo_report.old_closed'), 'format' => 'number'],
            ['key' => 'oldCloseRate', 'label' => __('reports.ceo_report.old_close_rate'), 'format' => 'percent'],
            ['key' => 'oldProductQty', 'label' => __('reports.ceo_report.old_products'), 'format' => 'number'],
            ['key' => 'oldEstRevenue', 'label' => __('reports.ceo_report.old_revenue'), 'format' => 'currency'],
            ['key' => 'totalEstRevenue', 'label' => __('reports.ceo_report.total_revenue'), 'format' => 'currency'],
            ['key' => 'salesKpi', 'label' => __('reports.ceo_report.kpi'), 'format' => 'currency'],
            ['key' => 'achievementRate', 'label' => __('reports.ceo_report.kpi_pct'), 'format' => 'percent'],
        ];

        if ($exported = $this->maybeExportReport(
            $request,
            $report['saleRows'],
            $columns,
            'bao-cao-ceo',
            [
                'title' => __('reports.ceo_report.title'),
                'date_from' => $filter->dateFrom?->toDateString(),
                'date_to' => $filter->dateTo?->toDateString(),
            ],
        )) {
            return $exported;
        }

        $filterOptions = app(\App\Services\FilterOptionsService::class);

        return Inertia::render('Admin/Reports/CeoReport', array_merge($this->reportPageProps($request, [
            'report' => $report,
            'routeUrl' => '/admin/reports/ceo',
        ]), [
            'filterFields' => $filterOptions->ceoReportFilterFields(),
        ]));
    }
}
