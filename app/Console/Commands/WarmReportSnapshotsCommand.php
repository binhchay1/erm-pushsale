<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Jobs\Reports\WarmReportSnapshotsForUserJob;
use App\Models\User;
use App\Services\Reporting\ReportSnapshotWarmupService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class WarmReportSnapshotsCommand extends Command
{
    protected $signature = 'reports:warm-snapshots
        {--company=}
        {--all-users : Warm every staff account; default only admin/leader accounts}
        {--queue : Dispatch one bounded job per user/date window instead of warming inline}';

    protected $description = 'Pre-compute durable snapshots from closed daily facts';

    public function handle(ReportSnapshotWarmupService $warmup): int
    {
        $users = User::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->unless($this->option('all-users'), fn ($q) => $q->where(function ($scope): void {
                $scope->where('role', UserRole::Admin->value)
                    ->orWhere('is_team_leader', true)
                    ->orWhere('org_level', 'head');
            }))
            ->orderBy('company_id')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return self::SUCCESS;
        }

        $now = CarbonImmutable::now(config('reporting.timezone'));
        $yesterday = $now->subDay();
        $windows = [
            'yesterday' => [$yesterday->toDateString(), $yesterday->toDateString()],
            'previous_month' => [
                $now->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
        ];

        if ($now->day > 1) {
            $windows['month_to_yesterday'] = [
                $now->startOfMonth()->toDateString(),
                $yesterday->toDateString(),
            ];
        }

        foreach ($users as $user) {
            foreach ($windows as $window => [$from, $to]) {
                if ($this->option('queue')) {
                    WarmReportSnapshotsForUserJob::dispatch((int) $user->id, $from, $to);
                    $this->line("Queued {$user->company_id}/{$user->id} {$window} {$from}..{$to}");
                    continue;
                }

                $warmed = $warmup->warmUserWindow($user, $from, $to);
                $this->line(sprintf(
                    '%s/%s %s %s..%s OK: %s',
                    $user->company_id,
                    $user->id,
                    $window,
                    $from,
                    $to,
                    implode(', ', $warmed),
                ));
            }
        }

        $this->info('Snapshot warming finished at '.now()->toDateTimeString());

        return self::SUCCESS;
    }
}
