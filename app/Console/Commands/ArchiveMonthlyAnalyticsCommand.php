<?php

namespace App\Console\Commands;

use App\Jobs\Reports\ArchiveMonthlyAnalyticsJob;
use App\Models\Company;
use App\Services\Reporting\MonthlyArchiveService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ArchiveMonthlyAnalyticsCommand extends Command
{
    protected $signature = 'reports:archive-month
        {month? : YYYY-MM, default previous month}
        {--company= : Company id}
        {--purge : Delete only purge-safe hot rows after checksum verification}
        {--dry-run : Count/checksum only}
        {--queue : Dispatch to reports queue}';

    protected $description = 'Copy source data into verified physical monthly archive tables';

    public function handle(MonthlyArchiveService $service): int
    {
        $month = $this->argument('month') ?: CarbonImmutable::now(config('reporting.timezone'))->subMonth()->format('Y-m');
        $companies = Company::query()
            ->when($this->option('company'), fn ($q) => $q->whereKey($this->option('company')))
            ->pluck('id');

        foreach ($companies as $companyId) {
            if ($this->option('queue')) {
                ArchiveMonthlyAnalyticsJob::dispatch((int) $companyId, $month, (bool) $this->option('purge'));
                $this->line("Queued company {$companyId}/{$month}");
                continue;
            }

            $result = $service->archiveCompanyMonth(
                (int) $companyId,
                $month,
                (bool) $this->option('purge'),
                (bool) $this->option('dry-run'),
            );

            foreach ($result as $table => $row) {
                $this->line(sprintf(
                    '%d %s -> %s [%s] source=%s archive=%s',
                    $companyId,
                    $table,
                    $row['archiveTable'] ?? '-',
                    $row['status'] ?? 'unknown',
                    $row['sourceRows'] ?? '-',
                    $row['archiveRows'] ?? '-',
                ));
            }
        }

        return self::SUCCESS;
    }
}
