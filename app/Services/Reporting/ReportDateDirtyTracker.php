<?php

namespace App\Services\Reporting;

use App\Models\Reporting\ReportDirtyDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportDateDirtyTracker
{
    /** @var array<string,bool> */
    private static array $availableTables = [];

    public function mark(
        ?int $companyId,
        CarbonInterface|string|null $date,
        string $reason,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): void {
        $this->markMany($companyId, [$date], $reason, $sourceType, $sourceId);
    }

    /**
     * Đánh dấu nhiều ngày trong một transaction/revision để tránh một lần lưu Order tạo 8-16 lượt
     * lock, cache invalidation và Redis increment riêng biệt.
     *
     * @param  iterable<CarbonInterface|string|null>  $dates
     */
    public function markMany(
        ?int $companyId,
        iterable $dates,
        string $reason,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): void {
        if (! config('reporting.enabled') || ! $companyId || ! $this->tableExists('report_dirty_dates')) {
            return;
        }

        $metricDates = collect($dates)
            ->map(fn ($date) => $this->normalizeDate($date))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($metricDates->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($companyId, $metricDates, $reason, $sourceType, $sourceId): void {
            foreach ($metricDates as $metricDate) {
                /** @var ReportDirtyDate|null $dirty */
                $dirty = ReportDirtyDate::query()
                    ->where('company_id', $companyId)
                    ->whereDate('metric_date', $metricDate)
                    ->lockForUpdate()
                    ->first();

                $values = [
                    'last_reason' => mb_substr($reason, 0, 120),
                    'source_type' => $sourceType ? mb_substr($sourceType, 0, 80) : null,
                    'source_id' => $sourceId,
                    'next_attempt_at' => now(),
                    'locked_at' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ];

                if (! $dirty) {
                    try {
                        DB::table('report_dirty_dates')->insert(array_merge($values, [
                            'company_id' => $companyId,
                            'metric_date' => $metricDate,
                            'event_count' => 1,
                            'attempts' => 0,
                            'created_at' => now(),
                        ]));
                    } catch (QueryException) {
                        // Hai webhook có thể cùng đánh dấu một ngày chưa tồn tại. Unique key
                        // quyết định winner; request còn lại chuyển thành atomic increment.
                        DB::table('report_dirty_dates')
                            ->where('company_id', $companyId)
                            ->whereDate('metric_date', $metricDate)
                            ->update(array_merge($values, [
                                'event_count' => DB::raw('event_count + 1'),
                            ]));
                    }
                } else {
                    DB::table('report_dirty_dates')
                        ->whereKey($dirty->id)
                        ->update(array_merge($values, [
                            'event_count' => DB::raw('event_count + 1'),
                        ]));
                }

                DB::table('report_daily_closures')
                    ->where('company_id', $companyId)
                    ->whereDate('metric_date', $metricDate)
                    ->update([
                        'status' => 'dirty',
                        'finalized_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        }, 3);

        $this->bumpCompanyRevision($companyId);
        $this->invalidateSnapshotsRange(
            $companyId,
            (string) $metricDates->first(),
            (string) $metricDates->last(),
        );
    }


    public function markArchiveMonthStale(
        ?int $companyId,
        string $sourceTable,
        CarbonInterface|string|null $date,
        string $reason,
    ): void {
        if (! config('reporting.archive.enabled') || ! $companyId || ! $this->tableExists('analytics_archive_manifests')) {
            return;
        }

        $definition = config("reporting.archive.sources.{$sourceTable}");
        $metricDate = $this->normalizeDate($date);
        if (! is_array($definition) || ! $metricDate) {
            return;
        }

        $month = CarbonImmutable::parse($metricDate, config('reporting.timezone'))->format('Y-m');
        $currentMonth = CarbonImmutable::now(config('reporting.timezone'))->format('Y-m');
        if ($month >= $currentMonth) {
            return;
        }

        DB::table('analytics_archive_manifests')
            ->where('company_id', $companyId)
            ->where('source_table', $sourceTable)
            ->where('archive_month', $month)
            ->update([
                'status' => DB::raw("CASE WHEN source_purged = 1 THEN 'late_rows_after_purge' ELSE 'stale' END"),
                'verified' => false,
                'last_error' => mb_substr($reason, 0, 65535),
                'updated_at' => now(),
            ]);
    }

    public function invalidateAllSnapshots(int $companyId): int
    {
        if (! $this->tableExists('report_query_snapshots')) {
            return 0;
        }

        $deleted = DB::table('report_query_snapshots')
            ->where('company_id', $companyId)
            ->delete();
        $this->bumpCompanyRevision($companyId);

        return $deleted;
    }

    public function invalidateSnapshots(int $companyId, CarbonInterface|string $date): int
    {
        $metricDate = $this->normalizeDate($date);
        if (! $metricDate) {
            return 0;
        }

        return $this->invalidateSnapshotsRange($companyId, $metricDate, $metricDate);
    }

    public function invalidateSnapshotsRange(int $companyId, string $dateFrom, string $dateTo): int
    {
        if (! $this->tableExists('report_query_snapshots')) {
            return 0;
        }

        return DB::table('report_query_snapshots')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($dateTo): void {
                $query->whereNull('date_from')->orWhereDate('date_from', '<=', $dateTo);
            })
            ->where(function ($query) use ($dateFrom): void {
                $query->whereNull('date_to')->orWhereDate('date_to', '>=', $dateFrom);
            })
            ->delete();
    }

    public function bumpCompanyRevision(int $companyId): int
    {
        try {
            $key = "reporting:company-revision:{$companyId}";
            Cache::add($key, 1, now()->addYears(10));

            return (int) Cache::increment($key);
        } catch (\Throwable) {
            // Redis/cache outage must never roll back an order, webhook or warehouse action.
            return 1;
        }
    }

    public function companyRevision(int $companyId): int
    {
        try {
            return (int) Cache::get("reporting:company-revision:{$companyId}", 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    private function tableExists(string $table): bool
    {
        if (self::$availableTables[$table] ?? false) {
            return true;
        }

        try {
            $exists = Schema::hasTable($table);
            if ($exists) {
                self::$availableTables[$table] = true;
            }

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeDate(CarbonInterface|string|null $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            $metricDate = $date instanceof CarbonInterface
                ? CarbonImmutable::instance($date)->timezone(config('reporting.timezone'))->toDateString()
                : CarbonImmutable::parse((string) $date, config('reporting.timezone'))->toDateString();
        } catch (\Throwable) {
            return null;
        }

        return $metricDate === '0000-00-00' ? null : $metricDate;
    }
}
