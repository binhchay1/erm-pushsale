<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\MarketingCampaignReportService;
use App\Support\ReportCsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignReportController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, MarketingCampaignReportService $service): Response|StreamedResponse
    {
        $filter = $this->reportFilters($request);
        $report = $service->build($filter, $request->user());

        if ($request->query('export') === 'csv') {
            return ReportCsvExporter::download(
                'bao-cao-chien-dich-marketing.csv',
                $report['rows'],
                $report['columns'],
            );
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
