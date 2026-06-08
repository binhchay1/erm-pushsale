<?php

namespace App\Http\Controllers\Admin\Marketing;

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

        return Inertia::render('Admin/Marketing/CampaignReport', $this->pageProps($request, [
            'report' => $report,
            'routeUrl' => '/admin/marketing/campaign-report',
            'budgetUpdateUrl' => '/admin/marketing/campaigns',
            'canEditBudget' => true,
        ]));
    }

    /** @return array<string, mixed> */
    private function pageProps(Request $request, array $data): array
    {
        $filterOptions = app(FilterOptionsService::class);

        return array_merge($this->reportPageProps($request, $data), [
            'filterFields' => $filterOptions->marketingCampaignReportFilterFields($request->user()),
        ]);
    }
}
