<?php

namespace App\Jobs\Reports;

use App\Models\User;
use App\Services\Reporting\ReportSnapshotWarmupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class WarmReportSnapshotsForUserJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1200;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $userId,
        public readonly string $from,
        public readonly string $to,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.reports_maintenance', 'reports-maintenance'));
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->from.':'.$this->to;
    }

    public function handle(ReportSnapshotWarmupService $warmup): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            throw new RuntimeException('Snapshot warmup user not found: '.$this->userId);
        }

        $warmup->warmUserWindow($user, $this->from, $this->to);
    }

    public function tags(): array
    {
        return ['reports', 'snapshots', 'warmup', 'user:'.$this->userId, 'from:'.$this->from, 'to:'.$this->to];
    }
}
