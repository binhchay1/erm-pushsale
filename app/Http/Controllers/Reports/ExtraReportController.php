<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\ExportsReportData;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
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
                'reportNav' => $this->reports->availableFor($user),
                'routeUrl' => $this->reports->basePathFor($user).'/'.$report,
                'filterFields' => $data['meta']['filterFields'],
                'cachedAt' => $cached['cachedAt'],
                'fromCache' => $cached['fromCache'],
            ],
        ));
    }
}
