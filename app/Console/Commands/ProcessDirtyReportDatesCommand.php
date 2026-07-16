<?php

namespace App\Console\Commands;

use App\Jobs\Reports\BuildDailyReportFactsJob;
use App\Models\Reporting\ReportDirtyDate;
use App\Services\Reporting\DailyReportAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class ProcessDirtyReportDatesCommand extends Command
{
    protected $signature = 'reports:process-dirty {--limit=} {--company=} {--queue}';

    protected $description = 'Rebuild historical dates changed by late webhook/COD/return/order updates';

    public function handle(DailyReportAggregator $aggregator): int
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
            $finalize = CarbonImmutable::parse($date, config('reporting.timezone'))->isBefore(today(config('reporting.timezone')));

            if ($this->option('queue')) {
                BuildDailyReportFactsJob::dispatch((int) $dirty->company_id, $date, $finalize);
                $dirty->update(['locked_at' => now()]);
                $this->line("Queued {$dirty->company_id}/{$date}");
                continue;
            }

            try {
                $dirty->update(['locked_at' => now(), 'attempts' => $dirty->attempts + 1]);
                $aggregator->rebuild((int) $dirty->company_id, $date, $finalize);
                $this->line("Rebuilt {$dirty->company_id}/{$date}");
            } catch (Throwable $e) {
                $dirty->refresh();
                $delay = min(360, 2 ** min(8, (int) $dirty->attempts));
                $dirty->update([
                    'locked_at' => null,
                    'next_attempt_at' => now()->addMinutes($delay),
                    'last_error' => mb_substr($e->getMessage(), 0, 65535),
                ]);
                $this->error("Failed {$dirty->company_id}/{$date}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
