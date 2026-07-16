<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
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
    use InteractsWithReportSnapshots;

    public function __invoke(Request $request, MarketingCampaignReportService $service): Response|StreamedResponse|HttpResponse
    {
        $filter = $this->reportFilters($request);
        $snapshot = $this->maybeCachedReport(
            $request,
            'admin-marketing-campaign-report',
            $filter,
            fn () => $service->build($filter, $request->user()),
        );
        $report = $snapshot['data'];

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

        return Inertia::render('Admin/Marketing/CampaignReport', $this->pageProps($request, [
            'report' => $report,
            'routeUrl' => '/admin/marketing/campaign-report',
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
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
