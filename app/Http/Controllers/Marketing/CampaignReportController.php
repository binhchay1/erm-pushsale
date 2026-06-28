<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\MarketingCampaignReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignReportController extends Controller
{
    use ExportsReportData;
    use InteractsWithReportFilters;

    public function __invoke(Request $request, MarketingCampaignReportService $service): Response|StreamedResponse|HttpResponse
    {
        $filter = $this->reportFilters($request);
        $report = $service->build($filter, $request->user());

        if ($exported = $this->maybeExportReport(
            $request,
            $report['rows'],
            $report['columns'],
            'bao-cao-chien-dich-marketing',
            [
                'title' => __('reports.campaign_report.title'),
                'subtitle' => __('reports.campaign_report.desc'),
                'date_from' => $filter->dateFrom?->toDateString(),
                'date_to' => $filter->dateTo?->toDateString(),
            ],
        )) {
            return $exported;
        }

        $filterOptions = app(FilterOptionsService::class);

        return Inertia::render('Admin/Marketing/CampaignReport', array_merge(
            $this->reportPageProps($request, [
                'report' => $report,
                'routeUrl' => '/marketing/campaign-report',
                'budgetUpdateUrl' => '/marketing/campaigns',
                'canEditBudget' => true,
            ]),
            ['filterFields' => $filterOptions->marketingCampaignReportFilterFields($request->user())],
        ));
    }
}
