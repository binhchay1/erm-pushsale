<?php

namespace App\Console\Commands;

use App\Jobs\Reports\ArchiveMonthlyAnalyticsJob;
use App\Models\Company;
use App\Services\Reporting\MonthlyArchiveService;
use Illuminate\Console\Command;

class ArchiveMonthlyAnalyticsCommand extends Command
{
    protected $signature = 'reports:archive-month
        {period? : YYYY (yearly, default) or YYYY-MM (monthly). Default = previous year/month by driver}
        {--company= : Company id}
        {--purge : Delete only purge-safe hot rows after checksum verification}
        {--dry-run : Count/checksum only}
        {--queue : Dispatch to reports queue}';

    protected $description = 'Copy source data into verified physical archive tables (yearly *_YYYY by default)';

    public function handle(MonthlyArchiveService $service): int
    {
        $period = $this->argument('period') ?: $service->defaultPeriod();
        $companies = Company::query()
            ->when($this->option('company'), fn ($q) => $q->whereKey($this->option('company')))
            ->pluck('id');

        foreach ($companies as $companyId) {
            if ($this->option('queue')) {
                ArchiveMonthlyAnalyticsJob::dispatch((int) $companyId, $period, (bool) $this->option('purge'));
                $this->line("Queued company {$companyId}/{$period}");
                continue;
            }

            $result = $service->archiveCompanyMonth(
                (int) $companyId,
                $period,
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
