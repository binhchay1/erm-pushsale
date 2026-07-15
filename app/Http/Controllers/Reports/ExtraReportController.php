<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\ReportSnapshotCache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtraReportController extends Controller
{
    use ExportsReportData;
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __construct(
        private readonly ExtraReportService $reports,
    ) {}

    public function __invoke(Request $request, string $report): Response|StreamedResponse|HttpResponse
    {
        $user = $request->user();

        abort_unless($this->reports->exists($report), 404);
        abort_unless($this->reports->canView($user, $report), 403);

        $filter = $this->reportFilters($request);
        $cached = $this->maybeCachedReport(
            $request,
            $report,
            $filter,
            fn () => $this->reports->build($report, $user, $filter),
            ReportSnapshotCache::isHeavyExtra($report),
        );
        $data = $cached['data'];

        $exportRows = $data['rows'];
        if ($data['totals']) {
            $exportRows[] = array_merge($data['totals'], ['_is_total' => true]);
        }

        if ($exported = $this->maybeExportReport(
            $request,
            $exportRows,
            $data['columns'],
            'bao-cao-'.$report,
            [
                'title' => $data['meta']['title'],
                'subtitle' => $data['meta']['description'] ?? '',
                'date_from' => $filter->dateFrom?->toDateString(),
                'date_to' => $filter->dateTo?->toDateString(),
            ],
        )) {
            return $exported;
        }

        return Inertia::render('Reports/ExtraReport', array_merge(
            $this->reportPageProps($request),
            [
                'meta' => $data['meta'],
                'columns' => $data['columns'],
                'rows' => $data['rows'],
                'totals' => $data['totals'],
                'extra' => $data['extra'] ?? [],
                'activeMenuCode' => $this->activeMenuCode($user->role, $report),
                'reportNav' => $this->reports->availableFor($user),
                'routeUrl' => $this->reports->basePathFor($user).'/'.$report,
                'filterFields' => $data['meta']['filterFields'],
                'cachedAt' => $cached['cachedAt'],
                'fromCache' => $cached['fromCache'],
            ],
        ));
    }
    private function activeMenuCode(UserRole $role, string $report): ?string
    {
        return match ($role) {
            UserRole::Admin => match ($report) {
                'sale-4' => '4.5.1',
                'sale-2' => '4.5.2',
                'sale-1' => '4.5.3',
                'sale-3' => '4.5.4',
                'warehouse-sales-summary' => '4.5.5',
                'warehouse-sales-v2' => '4.5.6',
                'sale-5' => '4.5.8',
                'kho-2' => '4.5.9',
                'product-conversion' => '6.3.9',
                'marketing-1' => '2.7.1',
                'marketing-3' => '2.7.5',
                'marketing-4' => '2.7.8',
                default => null,
            },
            UserRole::Sales => match ($report) {
                'sale-4' => '4.5.1',
                'sale-2' => '4.5.2',
                'sale-1' => '4.5.3',
                'sale-3' => '4.5.4',
                'warehouse-sales-summary' => '4.5.5',
                'warehouse-sales-v2' => '4.5.6',
                'sale-5' => '4.5.8',
                'kho-2' => '4.5.9',
                'product-conversion' => '4.5.10',
                default => null,
            },
            UserRole::Marketing => match ($report) {
                'marketing-1' => '2.7.1',
                'marketing-3' => '2.7.5',
                'kho-2' => '2.7.6',
                'product-conversion', 'marketing-2' => '2.7.7',
                'marketing-4' => '2.7.8',
                default => null,
            },
            UserRole::Warehouse => match ($report) {
                'kho-2' => '5.5.3',
                'warehouse-sales-summary' => '5.5.9',
                'warehouse-sales-v2' => '5.5.10',
                default => null,
            },
            UserRole::Accounting => match ($report) {
                'warehouse-sales-summary' => '6.3.2',
                'warehouse-sales-v2' => '6.3.3',
                'sale-3' => '6.3.6',
                'kho-2' => '6.3.8',
                'product-conversion' => '6.3.9',
                'sale-2' => '6.3.10',
                'marketing-1' => '6.3.11',
                'marketing-4' => '6.3.12',
                default => null,
            },
            default => null,
        };
    }

}
