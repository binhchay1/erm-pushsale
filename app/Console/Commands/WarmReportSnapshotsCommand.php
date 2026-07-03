<?php

namespace App\Console\Commands;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\ExtraReportService;
use App\Services\Reports\MarketingDashboardService;
use App\Services\Reports\ReportSnapshotCache;
use App\Services\Reports\TeamLeaderStatsService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WarmReportSnapshotsCommand extends Command
{
    protected $signature = 'reports:warm-snapshots {--company=}';

    protected $description = 'Pre-compute heavy report snapshots (end-of-day cache warm-up)';

    public function handle(
        ExtraReportService $extra,
        TeamLeaderStatsService $teamLeaders,
        MarketingDashboardService $marketingDashboard,
        ReportSnapshotCache $cache,
    ): int {
        $companyId = $this->option('company');

        $admins = User::query()
            ->where('role', UserRole::Admin)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found.');

            return self::SUCCESS;
        }

        $filter = ReportFilterData::fromRequest(
            Request::create('/', 'GET', ['preset' => 'today']),
        );

        foreach ($admins as $admin) {
            $this->info("Warming reports for company {$admin->company_id}...");

            foreach (ReportSnapshotCache::heavyExtraKeys() as $key) {
                if (! $extra->exists($key)) {
                    continue;
                }
                $cache->remember($key, $admin, $filter, fn () => $extra->build($key, $admin, $filter), forceRefresh: true);
                $this->line("  extra/{$key} OK");
            }

            $cache->remember('team-leaders', $admin, $filter, fn () => $teamLeaders->build($admin, $filter), forceRefresh: true);
            $this->line('  team-leaders OK');

            $cache->remember('marketing-dashboard', $admin, $filter, fn () => $marketingDashboard->build($filter), forceRefresh: true);
            $this->line('  marketing-dashboard OK');
        }

        $this->info('Done at '.Carbon::now()->toDateTimeString());

        return self::SUCCESS;
    }
}
