<?php

namespace App\Http\Controllers\Allocator;

use App\Enums\OrgLevel;
use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FilterOptionsService;
use App\Services\Reports\AllocatorReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ExportsReportData;
    use InteractsWithReportFilters;

    public function __invoke(
        Request $request,
        string $report,
        AllocatorReportService $service,
        FilterOptionsService $filterOptions,
    ): Response|StreamedResponse|HttpResponse {
        $user = $request->user();

        $report = in_array($report, [AllocatorReportService::REPORT_ALLOCATION, AllocatorReportService::REPORT_LOAD], true)
            ? $report
            : AllocatorReportService::REPORT_ALLOCATION;

        abort_unless(
            ! in_array($report, AllocatorReportService::LEADER_ONLY, true) || $this->isLeader($user),
            403,
        );

        $filter = $this->reportFilters($request);
        $data = $service->build($report, $filter);
        $columns = $this->exportColumns($report);
        $exportRows = $data['rows'];

        if ($data['totals']) {
            $label = __('reports.grand_total');
            $exportRows[] = $report === AllocatorReportService::REPORT_LOAD
                ? array_merge(['sale_name' => $label], $data['totals'])
                : array_merge(['date' => $label], $data['totals']);
        }

        $titleKey = $report === AllocatorReportService::REPORT_LOAD
            ? 'reports.allocator.load_title'
            : 'reports.allocator.allocation_title';

        if ($exported = $this->maybeExportReport(
            $request,
            $exportRows,
            $columns,
            'bao-cao-'.$report,
            [
                'title' => __($titleKey),
                'date_from' => $filter->dateFrom?->toDateString(),
                'date_to' => $filter->dateTo?->toDateString(),
            ],
        )) {
            return $exported;
        }

        return Inertia::render('Allocator/Reports', [
            'report' => $report,
            'data' => $data,
            'canViewLeaderReports' => $this->isLeader($user),
            'filters' => $filter->toInertia(),
            'filterFields' => ['date_from', 'date_to'],
            'filterOptions' => $filterOptions->forReports($user),
            'routeUrl' => '/allocator/reports/'.$report,
        ]);
    }

    /** @return list<array{key: string, label: string, format?: string}> */
    private function exportColumns(string $report): array
    {
        if ($report === AllocatorReportService::REPORT_LOAD) {
            return [
                ['key' => 'sale_name', 'label' => __('reports.allocator.col_sale')],
                ['key' => 'received', 'label' => __('reports.allocator.col_received'), 'format' => 'number'],
                ['key' => 'closed', 'label' => __('reports.allocator.col_closed'), 'format' => 'number'],
                ['key' => 'conversion', 'label' => __('reports.allocator.col_conversion'), 'format' => 'percent'],
                ['key' => 'revenue', 'label' => __('reports.allocator.col_revenue'), 'format' => 'currency'],
            ];
        }

        return [
            ['key' => 'date', 'label' => __('reports.allocator.col_date')],
            ['key' => 'total', 'label' => __('reports.allocator.col_total'), 'format' => 'number'],
            ['key' => 'assigned', 'label' => __('reports.allocator.col_assigned'), 'format' => 'number'],
            ['key' => 'pending', 'label' => __('reports.allocator.col_pending'), 'format' => 'number'],
            ['key' => 'duplicate', 'label' => __('reports.allocator.col_duplicate'), 'format' => 'number'],
            ['key' => 'failed', 'label' => __('reports.allocator.col_failed'), 'format' => 'number'],
            ['key' => 'allocation_rate', 'label' => __('reports.allocator.col_rate'), 'format' => 'percent'],
        ];
    }

    private function isLeader(User $user): bool
    {
        return $user->is_team_leader || $user->org_level === OrgLevel::Head;
    }
}
