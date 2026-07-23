<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reports\TeamLeaderStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamLeaderStatsController extends Controller
{
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __construct(
        private readonly TeamLeaderStatsService $service,
    ) {}

    public function __invoke(Request $request, FilterOptionsService $filterOptions): Response
    {
        $user = $request->user();
        $filter = $this->reportFilters($request);
        $cached = $this->maybeCachedReport(
            $request,
            'team-leaders',
            $filter,
            fn () => $this->service->build($user, $filter),
        );
        $data = $cached['data'];

        $routeUrl = $request->is('ld/marketing/thong-ke-truong-nhom')
            ? '/ld/marketing/thong-ke-truong-nhom'
            : ($request->routeIs('marketing.*') ? '/marketing/reports/team-leaders' : '/admin/reports/team-leaders');

        return Inertia::render('Reports/TeamLeaderStats', array_merge(
            $this->reportPageProps($request, [
                'filterFields' => $filterOptions->marketingLeaderStatsFilterFields(),
            ]),
            [
                'rows' => $data['rows'] ?? [],
                'totals' => $data['totals'] ?? [],
                'statusSummary' => $data['statusSummary'] ?? [],
                'routeUrl' => $routeUrl,
                'activeMenuCode' => $request->is('ld/marketing/thong-ke-truong-nhom') || $request->routeIs('marketing.*') ? '2.8.1' : '2.8.1',
                'cachedAt' => $cached['cachedAt'],
                'fromCache' => $cached['fromCache'],
            ],
        ));
    }
}
