<?php

namespace App\Jobs\Reports;

use App\Services\Reporting\MonthlyArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchiveMonthlyAnalyticsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 3600;

    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $companyId,
        public readonly string $month,
        public readonly bool $purge = false,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.reports_archive', 'reports-archive'));
    }

    public function uniqueId(): string
    {
        return $this->companyId.':'.$this->month;
    }

    public function handle(MonthlyArchiveService $service): void
    {
        $service->archiveCompanyMonth($this->companyId, $this->month, $this->purge);
    }

    public function tags(): array
    {
        return ['reports', 'monthly-archive', 'company:'.$this->companyId, 'month:'.$this->month];
    }
}
