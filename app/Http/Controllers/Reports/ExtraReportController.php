<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Services\Reports\ExtraReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExtraReportController extends Controller
{
    use InteractsWithReportFilters;

    public function __construct(
        private readonly ExtraReportService $reports,
    ) {}

    public function __invoke(Request $request, string $report): Response
    {
        $user = $request->user();

        abort_unless($this->reports->exists($report), 404);
        abort_unless($this->reports->canView($user, $report), 403);

        $filter = $this->reportFilters($request);
        $data = $this->reports->build($report, $user, $filter);

        return Inertia::render('Reports/ExtraReport', array_merge(
            $this->reportPageProps($request),
            [
                'meta' => $data['meta'],
                'columns' => $data['columns'],
                'rows' => $data['rows'],
                'totals' => $data['totals'],
                'reportNav' => $this->reports->availableFor($user),
                'routeUrl' => $this->reports->basePathFor($user).'/'.$report,
                // Mỗi báo cáo chỉ hiển thị bộ lọc phù hợp với nghiệp vụ của nó
                'filterFields' => $data['meta']['filterFields'],
            ],
        ));
    }
}
