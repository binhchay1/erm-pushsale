<?php

namespace App\Console\Commands;

use App\Jobs\Reports\UpdateDailyFactJob;
use App\Models\Reporting\ReportDirtyDate;
use App\Services\Reporting\SqlMarketingFactAggregator;
use Illuminate\Console\Command;
use Throwable;

class ProcessDirtyReportDatesCommand extends Command
{
    protected $signature = 'reports:process-dirty {--limit=} {--company=} {--queue}';

    protected $description = 'Rebuild only the mutated company/date reporting facts using SQL aggregation';

    public function handle(SqlMarketingFactAggregator $aggregator): int
    {
        $limit = (int) ($this->option('limit') ?: config('reporting.dirty_batch_size', 30));
        $rows = ReportDirtyDate::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('locked_at')->orWhere('locked_at', '<', now()->subMinutes(20)))
            ->orderBy('metric_date')
            ->limit(max(1, $limit))
            ->get();

        foreach ($rows as $dirty) {
            $date = $dirty->metric_date->toDateString();
            $companyId = (int) $dirty->company_id;

            if ($this->option('queue')) {
                UpdateDailyFactJob::dispatch($companyId, $date);
                $dirty->update(['locked_at' => now()]);
                $this->line("Queued {$companyId}/{$date}");
                continue;
            }

            try {
                $dirty->update(['locked_at' => now(), 'attempts' => $dirty->attempts + 1]);
                $aggregator->aggregateDate($date, $companyId);
                $dirty->delete();
                $this->line("Rebuilt {$companyId}/{$date}");
            } catch (Throwable $e) {
                $dirty->refresh();
                $delay = min(360, 2 ** min(8, (int) $dirty->attempts));
                $dirty->update([
                    'locked_at' => null,
                    'next_attempt_at' => now()->addMinutes($delay),
                    'last_error' => mb_substr($e->getMessage(), 0, 65535),
                ]);
                $this->error("Failed {$companyId}/{$date}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
