<?php

namespace App\Console\Commands;

use App\Jobs\Reports\VerifyReportFactsJob;
use App\Models\Company;
use App\Services\Reporting\DailyReportAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class VerifyReportFactsCommand extends Command
{
    protected $signature = 'reports:verify-facts
        {--company=}
        {--days=7}
        {--repair}
        {--queue : Dispatch one verification job per company/day}';

    protected $description = 'Verify source/fact checksums and completeness for closed historical reporting dates';

    public function handle(DailyReportAggregator $aggregator): int
    {
        $timezone = config('reporting.timezone');
        $days = max(1, (int) $this->option('days'));
        $to = CarbonImmutable::now($timezone)->startOfDay()->subDay();
        $from = $to->subDays($days - 1);
        $companyIds = Company::query()
            ->when($this->option('company'), fn ($q) => $q->whereKey($this->option('company')))
            ->pluck('id');

        $failed = 0;
        foreach ($companyIds as $companyId) {
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $date = $day->toDateString();

                if ($this->option('queue')) {
                    VerifyReportFactsJob::dispatch((int) $companyId, $date, (bool) $this->option('repair'));
                    $this->line("Queued verify {$companyId}/{$date}");
                    continue;
                }

                $verification = $aggregator->verify((int) $companyId, $date);
                $valid = (bool) ($verification['valid'] ?? false)
                    && ($verification['status'] ?? null) === 'closed';

                if ($valid) {
                    $this->line("PASS {$companyId}/{$date}");
                    continue;
                }

                $failed++;
                $this->error(sprintf(
                    'FAIL %d/%s status=%s source=%s facts=%s reason=%s',
                    $companyId,
                    $date,
                    (string) ($verification['status'] ?? 'missing'),
                    ($verification['sourceValid'] ?? false) ? 'ok' : 'mismatch',
                    ($verification['factsValid'] ?? false) ? 'ok' : 'mismatch',
                    (string) ($verification['reason'] ?? 'checksum_or_status'),
                ));

                if (! $this->option('repair')) {
                    continue;
                }

                try {
                    $aggregator->rebuild((int) $companyId, $date, true);
                    $this->line('  repaired');
                } catch (Throwable $e) {
                    $this->error('  repair failed: '.$e->getMessage());
                }
            }
        }

        return $failed > 0 && ! $this->option('repair') ? self::FAILURE : self::SUCCESS;
    }
}
