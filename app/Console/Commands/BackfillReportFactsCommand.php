<?php

namespace App\Console\Commands;

use App\Jobs\Reports\UpdateDailyFactJob;
use App\Services\Reporting\SqlMarketingFactAggregator;
use App\Services\Reporting\ReportFactSyncRangeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillReportFactsCommand extends Command
{
    protected $signature = 'reports:backfill-facts
        {--from= : YYYY-MM-DD; omitted = auto-detect earliest DB date}
        {--to= : YYYY-MM-DD; omitted = auto-detect latest DB date, capped at yesterday}
        {--company= : Company id}
        {--queue : Dispatch one SQL job per company/day}
        {--missing-only : Skip days that already have marketing packet facts}
        {--force : Rebuild even when facts already exist}';

    protected $description = 'Backfill report facts with SQL aggregation, without loading raw rows into PHP memory';

    public function handle(SqlMarketingFactAggregator $aggregator, ReportFactSyncRangeResolver $resolver): int
    {
        $timezone = config('reporting.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $missingOnly = (bool) $this->option('missing-only') && ! (bool) $this->option('force');

        $ranges = $resolver->ranges(
            companyId: $companyId,
            from: $this->option('from') ?: null,
            to: $this->option('to') ?: null,
            includeToday: false,
        );

        if ($ranges->isEmpty()) {
            $this->warn('No historical source data found for backfill.');
            return self::SUCCESS;
        }

        foreach ($ranges as $range) {
            $company = (int) $range['company_id'];
            $from = $range['from'];
            $to = $range['to']->min($today->subDay());

            if ($from->isAfter($to)) {
                continue;
            }

            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $date = $day->toDateString();

                if ($missingOnly && $this->hasMarketingFacts($company, $date)) {
                    $this->line("{$company} {$date} SKIP existing");
                    continue;
                }

                if ($this->option('queue')) {
                    UpdateDailyFactJob::dispatch($company, $date);
                    $this->line("{$company} {$date} QUEUED");
                } else {
                    $result = $aggregator->aggregateDate($date, $company);
                    $this->line(sprintf(
                        '%d %s OK marketing=%d packets=%d',
                        $company,
                        $date,
                        (int) $result['marketing_fact_rows'],
                        (int) $result['marketing_packet_fact_rows'],
                    ));
                }
            }
        }

        return self::SUCCESS;
    }

    private function hasMarketingFacts(int $companyId, string $date): bool
    {
        return \Illuminate\Support\Facades\DB::table('report_daily_marketing_packet_facts')
            ->where('company_id', $companyId)
            ->whereDate('metric_date', $date)
            ->exists();
    }
}
