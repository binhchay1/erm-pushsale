<?php

namespace App\Jobs\Reports;

use App\Services\Reporting\DailyReportAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class VerifyReportFactsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1200;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $companyId,
        public readonly string $date,
        public readonly bool $repair = false,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.reports_maintenance', 'reports-maintenance'));
    }

    public function uniqueId(): string
    {
        return $this->companyId.':'.$this->date.':'.($this->repair ? 'repair' : 'verify');
    }

    public function handle(DailyReportAggregator $aggregator): void
    {
        $verification = $aggregator->verify($this->companyId, $this->date);
        $valid = (bool) ($verification['valid'] ?? false)
            && ($verification['status'] ?? null) === 'closed';

        if ($valid) {
            return;
        }

        if ($this->repair) {
            $aggregator->rebuild($this->companyId, $this->date, true);
            return;
        }

        throw new RuntimeException(sprintf(
            'Report facts verification failed company=%d date=%s status=%s reason=%s',
            $this->companyId,
            $this->date,
            (string) ($verification['status'] ?? 'missing'),
            (string) ($verification['reason'] ?? 'checksum_or_status'),
        ));
    }

    public function tags(): array
    {
        return [
            'reports',
            'verify-facts',
            'company:'.$this->companyId,
            'date:'.$this->date,
            $this->repair ? 'repair' : 'verify',
        ];
    }
}
