<?php

namespace App\Services\Reporting;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\MarketingDashboardService;
use App\Services\Reports\ReportSnapshotCache;
use App\Services\Reports\TeamLeaderStatsService;
use Illuminate\Http\Request;

class ReportSnapshotWarmupService
{
    public function __construct(
        private readonly ExtraReportService $extra,
        private readonly TeamLeaderStatsService $teamLeaders,
        private readonly MarketingDashboardService $marketingDashboard,
        private readonly ReportSnapshotCache $cache,
    ) {}

    /**
     * @return list<string>
     */
    public function warmUserWindow(User $user, string $from, string $to): array
    {
        $filter = ReportFilterData::fromRequest(Request::create('/', 'GET', [
            'date_from' => $from,
            'date_to' => $to,
        ]), $user);

        $warmed = [];
        $dashboardKey = $user->role->value.'-dashboard';
        $this->cache->remember(
            $dashboardKey,
            $user,
            $filter,
            fn () => DashboardStatsService::snapshotFor($user, $filter),
            forceRefresh: true,
        );
        $warmed[] = $dashboardKey;

        foreach (ReportSnapshotCache::heavyExtraKeys() as $key) {
            if (! $this->extra->exists($key) || ! $this->extra->canView($user, $key)) {
                continue;
            }

            $this->cache->remember(
                $key,
                $user,
                $filter,
                fn () => $this->extra->build($key, $user, $filter),
                forceRefresh: true,
            );
            $warmed[] = 'extra/'.$key;
        }

        if ($user->role === UserRole::Admin || ($user->role === UserRole::Marketing && $user->is_team_leader)) {
            $this->cache->remember(
                'team-leaders',
                $user,
                $filter,
                fn () => $this->teamLeaders->build($user, $filter),
                forceRefresh: true,
            );
            $warmed[] = 'team-leaders';
        }

        if ($user->role === UserRole::Admin) {
            $this->cache->remember(
                'marketing-dashboard',
                $user,
                $filter,
                fn () => $this->marketingDashboard->build($filter),
                forceRefresh: true,
            );
            $warmed[] = 'marketing-dashboard';
        }

        return $warmed;
    }
}
