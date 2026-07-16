<?php

namespace App\Jobs\Reports;

use App\Models\Reporting\ReportQuerySnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneReportSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $limit = 5000)
    {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.reports_maintenance', 'reports-maintenance'));
    }

    public function handle(): void
    {
        $limit = max(100, $this->limit);
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

            ReportQuerySnapshot::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $limit);
    }

    public function tags(): array
    {
        return ['reports', 'snapshots', 'prune'];
    }
}
