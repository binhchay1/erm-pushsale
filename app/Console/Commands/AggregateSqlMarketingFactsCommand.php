<?php

namespace App\Console\Commands;

use App\Jobs\Reports\UpdateDailyFactJob;
use App\Services\Reporting\SqlMarketingFactAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AggregateSqlMarketingFactsCommand extends Command
{
    protected $signature = 'reports:aggregate-sql
        {date? : YYYY-MM-DD. Omitted = today unless --all/--from/--to is used.}
        {--company= : Company id}
        {--from= : Start date for a bounded SQL backfill}
        {--to= : End date for a bounded SQL backfill}
        {--all : Detect all dates available in DB and aggregate them}
        {--queue : Dispatch one SQL aggregation job per company/day}
        {--dry-run : Print the plan without writing facts}';

    protected $description = 'Aggregate reporting facts with raw INSERT SELECT SQL without loading raw rows into PHP memory.';

    public function handle(SqlMarketingFactAggregator $aggregator): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $dryRun = (bool) $this->option('dry-run');
        $queue = (bool) $this->option('queue');

        $plan = $this->plan($companyId);
        if ($plan->isEmpty()) {
            $this->warn('No dates matched for SQL aggregation.');
            return self::SUCCESS;
        }

        foreach ($plan as $item) {
            $company = (int) $item['company_id'];
            $date = (string) $item['date'];
            $this->line(($dryRun ? 'PLAN' : ($queue ? 'QUEUE' : 'RUN'))." company={$company} date={$date}");

            if ($dryRun) {
                continue;
            }

            if ($queue) {
                UpdateDailyFactJob::dispatch($company, $date);
                continue;
            }

            $result = $aggregator->aggregateDate($date, $company);
            $this->line(sprintf(
                'DONE company=%d date=%s marketing=%d packets=%d%s',
                $company,
                $date,
                (int) $result['marketing_fact_rows'],
                (int) $result['marketing_packet_fact_rows'],
                $result['locked'] ? ' locked' : '',
            ));
        }

        return self::SUCCESS;
    }

    /** @return Collection<int,array{company_id:int,date:string}> */
    private function plan(?int $companyId): Collection
    {
        $timezone = config('reporting.timezone');
        $dateArg = $this->argument('date');
        $fromArg = $this->option('from');
        $toArg = $this->option('to');

        if ($dateArg) {
            $date = CarbonImmutable::parse($dateArg, $timezone)->toDateString();
            return $this->companyIds($companyId)->map(fn (int $id): array => ['company_id' => $id, 'date' => $date]);
        }

        if ($fromArg || $toArg) {
            $from = CarbonImmutable::parse($fromArg ?: $toArg, $timezone)->startOfDay();
            $to = CarbonImmutable::parse($toArg ?: $fromArg, $timezone)->startOfDay();
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return $this->companyIds($companyId)->flatMap(function (int $id) use ($from, $to): array {
                $items = [];
                for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                    $items[] = ['company_id' => $id, 'date' => $day->toDateString()];
                }

                return $items;
            })->values();
        }

        if (! (bool) $this->option('all')) {
            $date = CarbonImmutable::now($timezone)->toDateString();
            return $this->companyIds($companyId)->map(fn (int $id): array => ['company_id' => $id, 'date' => $date]);
        }

        return $this->detectSourceRanges($companyId)->flatMap(function (object $range) use ($timezone): array {
            $from = CarbonImmutable::parse($range->first_date, $timezone)->startOfDay();
            $to = CarbonImmutable::parse($range->last_date, $timezone)->startOfDay();
            $items = [];
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $items[] = ['company_id' => (int) $range->company_id, 'date' => $day->toDateString()];
            }

            return $items;
        })->values();
    }

    /** @return Collection<int,int> */
    private function companyIds(?int $companyId): Collection
    {
        if ($companyId) {
            return collect([$companyId]);
        }

        return DB::table('companies')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id);
    }

    /** @return Collection<int,object> */
    private function detectSourceRanges(?int $companyId): Collection
    {
        $companyWhereA = $companyId ? 'WHERE company_id = ?' : '';
        $companyWhereB = $companyId ? 'AND lc.company_id = ?' : '';
        $bindings = [];
        if ($companyId) {
            $bindings[] = $companyId;
            $bindings[] = $companyId;
            $bindings[] = $companyId;
        }

        return collect(DB::select(<<<SQL
SELECT
    company_id,
    MIN(metric_date) AS first_date,
    MAX(metric_date) AS last_date
FROM (
    SELECT company_id, DATE(created_at) AS metric_date
    FROM lead_ingestions
    {$companyWhereA}
    UNION ALL
    SELECT company_id, DATE(created_at) AS metric_date
    FROM orders
    {$companyWhereA}
    UNION ALL
    SELECT lc.company_id, DATE(ie.created_at) AS metric_date
    FROM inbound_events ie
    INNER JOIN landing_connection_sources lcs
        ON ie.channel = CONCAT('landing-connection:', lcs.landing_connection_id, ':source:', lcs.id)
    INNER JOIN landing_connections lc ON lc.id = lcs.landing_connection_id
    WHERE ie.source = 'landing_webhook'
      {$companyWhereB}
) source_dates
WHERE company_id IS NOT NULL
  AND metric_date IS NOT NULL
GROUP BY company_id
ORDER BY company_id
SQL, $bindings));
    }
}
