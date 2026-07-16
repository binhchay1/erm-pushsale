<?php

namespace App\Jobs\Reports;

use App\Services\Reporting\DailyReportAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildDailyReportFactsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 1200;

    public function __construct(
        public readonly int $companyId,
        public readonly string $date,
        public readonly bool $finalize = false,
    ) {
        $this->onConnection('redis');

        $isLiveDay = $this->date === now(config('reporting.timezone'))->toDateString();
        $queue = (! $this->finalize && $isLiveDay)
            ? config('saleops.queues.reports_live', 'reports-live')
            : config('saleops.queues.reports_history', 'reports-history');

        $this->timeout = $isLiveDay ? 900 : 1800;
        $this->onQueue($queue);
    }

    public function uniqueId(): string
    {
        return $this->companyId.':'.$this->date.':'.($this->finalize ? 'close' : 'live');
    }

    public function handle(DailyReportAggregator $aggregator): void
    {
        $aggregator->rebuild($this->companyId, $this->date, $this->finalize);
    }

    public function tags(): array
    {
        return [
            'reports',
            'daily-facts',
            'company:'.$this->companyId,
            'date:'.$this->date,
            $this->finalize ? 'finalize' : 'live',
        ];
    }
}
