<?php

namespace App\Observers\Concerns;

use App\Jobs\Reports\UpdateDailyFactJob;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait DispatchesSqlDailyFacts
{
    protected function dispatchSqlFactRefresh(int $companyId, mixed $date): void
    {
        if ($companyId <= 0 || ! $date) {
            return;
        }

        $factDate = CarbonImmutable::parse($date, config('reporting.timezone'))->toDateString();

        UpdateDailyFactJob::dispatch($companyId, $factDate)
            ->delay(now()->addSeconds(20))
            ->afterCommit();
    }

    protected function dispatchCurrentAndOriginalDate(Model $model, int $companyId, string $dateColumn = 'created_at'): void
    {
        $this->dispatchSqlFactRefresh($companyId, $model->getAttribute($dateColumn));

        $original = $model->getOriginal($dateColumn);
        if ($original && (string) $original !== (string) $model->getAttribute($dateColumn)) {
            $this->dispatchSqlFactRefresh($companyId, $original);
        }
    }

    protected function companyIdFromLandingChannel(?string $channel): int
    {
        if (! $channel) {
            return 0;
        }

        $row = DB::table('landing_connection_sources as lcs')
            ->join('landing_connections as lc', 'lc.id', '=', 'lcs.landing_connection_id')
            ->whereRaw("CONCAT('landing-connection:', lcs.landing_connection_id, ':source:', lcs.id) = ?", [$channel])
            ->value('lc.company_id');

        return (int) ($row ?: 0);
    }
}
