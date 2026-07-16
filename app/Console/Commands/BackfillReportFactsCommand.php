<?php

namespace App\Console\Commands;

use App\Jobs\Reports\BuildDailyReportFactsJob;
use App\Models\Company;
use App\Services\Reporting\DailyReportAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillReportFactsCommand extends Command
{
    protected $signature = 'reports:backfill-facts
        {--from= : YYYY-MM-DD}
        {--to= : YYYY-MM-DD}
        {--company= : Company id}
        {--queue : Dispatch one job per company/day}';

    protected $description = 'Backfill historical daily facts without changing source business records';

    public function handle(DailyReportAggregator $aggregator): int
    {
        $from = CarbonImmutable::parse($this->option('from') ?: now()->subMonth()->startOfMonth(), config('reporting.timezone'))->startOfDay();
        $to = CarbonImmutable::parse($this->option('to') ?: yesterday(), config('reporting.timezone'))->startOfDay();

        if ($from->isAfter($to)) {
            $this->error('--from must be before or equal to --to.');
            return self::FAILURE;
        }

        $companies = Company::query()
            ->when($this->option('company'), fn ($q) => $q->whereKey($this->option('company')))
            ->pluck('id');

        foreach ($companies as $companyId) {
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                if ($this->option('queue')) {
                    BuildDailyReportFactsJob::dispatch((int) $companyId, $day->toDateString(), true);
                } else {
                    $aggregator->rebuild((int) $companyId, $day, true);
                }
                $this->line("{$companyId} {$day->toDateString()} OK");
            }
        }

        return self::SUCCESS;
    }
}
