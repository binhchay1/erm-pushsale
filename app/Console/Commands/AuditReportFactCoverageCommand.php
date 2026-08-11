<?php

namespace App\Console\Commands;

use App\Services\Reporting\ReportFactCoverage;
use Illuminate\Console\Command;

class AuditReportFactCoverageCommand extends Command
{
    protected $signature = 'reports:audit-fact-coverage {--json : Output machine-readable JSON}';

    protected $description = 'Audit report fact tables, dimensions, metrics, and known live-only filters.';

    public function handle(ReportFactCoverage $coverage): int
    {
        $contract = $coverage->contract();

        if ($this->option('json')) {
            $this->line(json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info('Report fact coverage');
        $this->newLine();

        $rows = [];
        foreach ($contract['database'] as $key => $status) {
            $rows[] = [
                $key,
                $status['table'],
                $status['ready'] ? 'READY' : 'MISSING',
                $status['missing_columns'] ? implode(', ', $status['missing_columns']) : '—',
            ];
        }

        $this->table(['Fact', 'Table', 'Status', 'Missing columns'], $rows);

        $this->newLine();
        $this->line('<comment>Hybrid policy</comment>');
        foreach ($contract['hybrid_policy'] as $key => $value) {
            $this->line("- {$key}: {$value}");
        }

        $this->newLine();
        $this->line('<comment>Filters that intentionally use live/detail query</comment>');
        foreach ($contract['live_only_filters'] as $filter) {
            $this->line('- '.$filter);
        }

        return self::SUCCESS;
    }
}
