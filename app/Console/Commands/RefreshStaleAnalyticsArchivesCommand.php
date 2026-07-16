<?php

namespace App\Console\Commands;

use App\Jobs\Reports\ArchiveMonthlyAnalyticsJob;
use App\Models\Reporting\AnalyticsArchiveManifest;
use App\Services\Reporting\MonthlyArchiveService;
use Illuminate\Console\Command;

class RefreshStaleAnalyticsArchivesCommand extends Command
{
    protected $signature = 'reports:refresh-stale-archives
        {--limit=12 : Maximum company/month groups per run}
        {--company= : Company id}
        {--queue : Dispatch to reports queue}';

    protected $description = 'Rebuild monthly archives marked stale by late source changes';

    public function handle(MonthlyArchiveService $service): int
    {
        $groups = AnalyticsArchiveManifest::query()
            ->select(['company_id', 'archive_month'])
            ->whereIn('status', ['stale', 'source_changed_retry_required', 'verification_failed'])
            ->where('source_purged', false)
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->groupBy(['company_id', 'archive_month'])
            ->orderBy('archive_month')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($groups as $group) {
            $companyId = (int) $group->company_id;
            $month = (string) $group->archive_month;

            if ($this->option('queue')) {
                ArchiveMonthlyAnalyticsJob::dispatch($companyId, $month, false);
                $this->line("Queued stale archive {$companyId}/{$month}");
                continue;
            }

            $service->archiveCompanyMonth($companyId, $month, false);
            $this->line("Refreshed stale archive {$companyId}/{$month}");
        }

        if ($groups->isEmpty()) {
            $this->line('No stale monthly archives.');
        }

        return self::SUCCESS;
    }
}
