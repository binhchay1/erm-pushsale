<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
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

    public function __invoke(Request $request): Response
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

        $filterFields = ['date_from', 'date_to', 'date_type'];
        if ($user->role === UserRole::Admin) {
            $filterFields[] = 'marketer_id';
        }

        $base = $request->routeIs('marketing.*') ? '/marketing/reports/team-leaders' : '/admin/reports/team-leaders';

        return Inertia::render('Reports/TeamLeaderStats', array_merge(
            $this->reportPageProps($request, ['filterFields' => $filterFields]),
            [
                'rows' => $data['rows'],
                'totals' => $data['totals'],
                'routeUrl' => $base,
                'cachedAt' => $cached['cachedAt'],
                'fromCache' => $cached['fromCache'],
            ],
        ));
    }
}
