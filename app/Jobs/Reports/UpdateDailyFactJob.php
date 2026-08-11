<?php

namespace App\Jobs\Reports;

use App\Services\Reporting\SqlMarketingFactAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDailyFactJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $companyId,
        public readonly string $date,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.reports_live', 'reports-live'));
    }

    public function uniqueId(): string
    {
        return "sql-daily-fact:{$this->companyId}:{$this->date}";
    }

    public function handle(SqlMarketingFactAggregator $aggregator): void
    {
        $aggregator->aggregateDate($this->date, $this->companyId);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return [
            'reports',
            'sql-facts',
            'company:'.$this->companyId,
            'date:'.$this->date,
        ];
    }
}
