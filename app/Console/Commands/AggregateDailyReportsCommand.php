<?php

namespace App\Console\Commands;

use App\Jobs\Reports\BuildDailyReportFactsJob;
use App\Models\Company;
use App\Services\Reporting\DailyReportAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AggregateDailyReportsCommand extends Command
{
    protected $signature = 'reports:aggregate-daily
        {date? : YYYY-MM-DD; default today}
        {--company= : Company id}
        {--close : Mark day immutable after rebuild}
        {--queue : Dispatch to reports queue instead of running inline}';

    protected $description = 'Build idempotent daily report facts for one business day';

    public function handle(DailyReportAggregator $aggregator): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?: 'today', config('reporting.timezone'))->toDateString();
        $companyIds = Company::query()
            ->when($this->option('company'), fn ($q) => $q->whereKey($this->option('company')))
            ->pluck('id');

        if ($companyIds->isEmpty()) {
            $this->warn('No companies matched.');
            return self::SUCCESS;
        }

        foreach ($companyIds as $companyId) {
            if ($this->option('queue')) {
                BuildDailyReportFactsJob::dispatch((int) $companyId, $date, (bool) $this->option('close'));
                $this->line("Queued company {$companyId} / {$date}");
                continue;
            }

            $result = $aggregator->rebuild((int) $companyId, $date, (bool) $this->option('close'));
            $this->line(sprintf(
                'Company %d %s: lead=%d raw_packet=%d order=%d product=%d cashflow=%d inventory=%d [%s]',
                $companyId,
                $date,
                $result['leadRows'],
                $result['marketingPacketRows'] ?? 0,
                $result['orderRows'],
                $result['productRows'],
                $result['cashflowRows'],
                $result['inventoryRows'],
                $result['status'],
            ));
        }

        return self::SUCCESS;
    }
}
