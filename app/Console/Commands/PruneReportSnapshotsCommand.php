<?php

namespace App\Console\Commands;

use App\Jobs\Reports\PruneReportSnapshotsJob;
use App\Models\Reporting\ReportQuerySnapshot;
use Illuminate\Console\Command;

class PruneReportSnapshotsCommand extends Command
{
    protected $signature = 'reports:prune-snapshots {--limit=5000} {--queue : Dispatch pruning to the reporting maintenance queue}';

    protected $description = 'Delete expired durable report result snapshots in bounded batches';

    public function handle(): int
    {
        $limit = max(100, (int) $this->option('limit'));

        if ($this->option('queue')) {
            PruneReportSnapshotsJob::dispatch($limit);
            $this->info("Queued pruning expired report snapshots with limit {$limit}.");
            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = ReportQuerySnapshot::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += ReportQuerySnapshot::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $limit);

        $this->info("Deleted {$deleted} expired report snapshots.");

        return self::SUCCESS;
    }
}
