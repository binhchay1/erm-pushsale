<?php

namespace App\Services\Reporting;

use App\Models\Reporting\AnalyticsArchiveManifest;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class MonthlyArchiveService
{
    /** @return array<string,mixed> */
    public function archiveCompanyMonth(
        int $companyId,
        string $month,
        bool $purge = false,
        bool $dryRun = false,
    ): array {
        $period = CarbonImmutable::createFromFormat('!Y-m', $month, config('reporting.timezone'));
        if (! $period || $period->format('Y-m') !== $month) {
            throw new InvalidArgumentException('Month must use YYYY-MM format.');
        }

        $from = $period->startOfMonth();
        $to = $period->endOfMonth()->endOfDay();
        $results = [];

        foreach (config('reporting.archive.sources', []) as $sourceTable => $definition) {
            if (! Schema::hasTable($sourceTable)) {
                $results[$sourceTable] = ['status' => 'skipped', 'reason' => 'source_table_missing'];
                continue;
            }

            $lock = Cache::lock("reporting:archive:{$companyId}:{$sourceTable}:{$month}", 7200);
            if (! $lock->get()) {
                $results[$sourceTable] = ['status' => 'locked', 'reason' => 'archive_already_running'];
                continue;
            }

            try {
                $results[$sourceTable] = $this->archiveTable(
                    $companyId,
                    $sourceTable,
                    (string) $definition['date_column'],
                    $from,
                    $to,
                    $month,
                    $purge && (bool) ($definition['purge_safe'] ?? false),
                    $dryRun,
                );
            } finally {
                $lock->release();
            }
        }

        return $results;
    }

    /** @return array<string,mixed> */
    private function archiveTable(
        int $companyId,
        string $sourceTable,
        string $dateColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $month,
        bool $purge,
        bool $dryRun,
    ): array {
        $archiveTable = $this->archiveTableName($sourceTable, $month);
        $sourceQuery = DB::table($sourceTable)
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to]);
        $sourceRows = (clone $sourceQuery)->count();
        $sourceChecksum = $this->checksum($sourceTable, $companyId, $dateColumn, $from, $to);

        $manifest = AnalyticsArchiveManifest::query()->updateOrCreate(
            ['company_id' => $companyId, 'source_table' => $sourceTable, 'archive_month' => $month],
            [
                'archive_table' => $archiveTable,
                'status' => $dryRun ? 'dry_run' : 'copying',
                'source_rows' => $sourceRows,
                'source_checksum' => $sourceChecksum,
                'last_error' => null,
            ],
        );

        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'sourceRows' => $sourceRows,
                'sourceChecksum' => $sourceChecksum,
                'archiveTable' => $archiveTable,
            ];
        }

        try {
            if ($this->supportsMonthlyTables()) {
                $this->createMonthlyTable($sourceTable, $archiveTable);
                $this->copyToMonthlyTable($sourceTable, $archiveTable, $companyId, $dateColumn, $from, $to);
                $archiveRows = DB::table($archiveTable)
                    ->where('company_id', $companyId)
                    ->whereBetween($dateColumn, [$from, $to])
                    ->count();
                $archiveChecksum = $this->checksum($archiveTable, $companyId, $dateColumn, $from, $to);
            } else {
                [$archiveRows, $archiveChecksum] = $this->copyToColdRecords(
                    $sourceTable,
                    $companyId,
                    $dateColumn,
                    $from,
                    $to,
                    $month,
                );
                $archiveTable = 'analytics_cold_records';
            }

            // Re-read source after copy. A row may have arrived or changed while chunks were copied.
            // In that case this run must never be marked verified and purge is forbidden.
            $currentSourceRows = (clone $sourceQuery)->count();
            $currentSourceChecksum = $this->checksum($sourceTable, $companyId, $dateColumn, $from, $to);
            $sourceStable = $sourceRows === $currentSourceRows
                && hash_equals($sourceChecksum, $currentSourceChecksum);
            $manifestStillCopying = AnalyticsArchiveManifest::query()
                ->whereKey($manifest->id)
                ->where('status', 'copying')
                ->exists();
            $verified = $sourceStable
                && $manifestStillCopying
                && $currentSourceRows === $archiveRows
                && hash_equals($currentSourceChecksum, $archiveChecksum);
            $sourceRows = $currentSourceRows;
            $sourceChecksum = $currentSourceChecksum;
            $sourcePurged = false;

            if ($purge) {
                if (! config('reporting.archive.allow_purge')) {
                    throw new InvalidArgumentException('Archive purge is disabled by REPORTING_ARCHIVE_ALLOW_PURGE.');
                }
                if (! $verified) {
                    throw new InvalidArgumentException('Cannot purge: archive row count/checksum verification failed.');
                }

                $deleted = DB::transaction(fn () => DB::table($sourceTable)
                    ->where('company_id', $companyId)
                    ->whereBetween($dateColumn, [$from, $to])
                    ->delete());
                $sourcePurged = $deleted === $sourceRows;
            }

            $status = $verified ? 'verified' : ($sourceStable ? 'verification_failed' : 'source_changed_retry_required');
            $updated = AnalyticsArchiveManifest::query()
                ->whereKey($manifest->id)
                ->where('status', 'copying')
                ->update([
                    'archive_table' => $archiveTable,
                    'status' => $status,
                    'source_rows' => $sourceRows,
                    'source_checksum' => $sourceChecksum,
                    'archive_rows' => $archiveRows,
                    'archive_checksum' => $archiveChecksum,
                    'verified' => $verified,
                    'source_purged' => $sourcePurged,
                    'archived_at' => now(),
                    'verified_at' => $verified ? now() : null,
                    'purged_at' => $sourcePurged ? now() : null,
                    'last_error' => $verified ? null : ($sourceStable
                        ? 'Row count or checksum mismatch.'
                        : 'Source changed during archive copy; retry required.'),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                $status = (string) AnalyticsArchiveManifest::query()->whereKey($manifest->id)->value('status');
                $verified = false;
            }

            return [
                'status' => $status,
                'sourceRows' => $sourceRows,
                'archiveRows' => $archiveRows,
                'sourceChecksum' => $sourceChecksum,
                'archiveChecksum' => $archiveChecksum,
                'verified' => $verified,
                'sourcePurged' => $sourcePurged,
                'archiveTable' => $archiveTable,
            ];
        } catch (Throwable $e) {
            $manifest->update(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 65535)]);
            throw $e;
        }
    }

    private function supportsMonthlyTables(): bool
    {
        if (config('reporting.archive.driver') !== 'monthly_tables') {
            return false;
        }

        return in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true);
    }

    private function createMonthlyTable(string $sourceTable, string $archiveTable): void
    {
        $connection = DB::connection();
        $source = $this->quoteIdentifier($connection, $sourceTable);
        $archive = $this->quoteIdentifier($connection, $archiveTable);

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement("CREATE TABLE IF NOT EXISTS {$archive} LIKE {$source}");
            return;
        }

        $connection->statement("CREATE TABLE IF NOT EXISTS {$archive} (LIKE {$source} INCLUDING ALL)");
    }

    private function copyToMonthlyTable(
        string $sourceTable,
        string $archiveTable,
        int $companyId,
        string $dateColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        $columns = Schema::getColumnListing($sourceTable);
        DB::connection()->disableQueryLog();

        DB::table($archiveTable)
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to])
            ->delete();

        // Copy theo dải ID để không giữ một transaction/lock khổng lồ cho cả tháng.
        DB::table($sourceTable)
            ->select('id')
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to])
            ->orderBy('id')
            ->chunkById((int) config('reporting.archive.copy_chunk_size', 2000), function ($ids) use (
                $sourceTable,
                $archiveTable,
                $companyId,
                $dateColumn,
                $from,
                $to,
                $columns,
            ): void {
                $minId = (int) $ids->min('id');
                $maxId = (int) $ids->max('id');

                DB::transaction(function () use (
                    $sourceTable,
                    $archiveTable,
                    $companyId,
                    $dateColumn,
                    $from,
                    $to,
                    $columns,
                    $minId,
                    $maxId,
                ): void {
                    DB::table($archiveTable)->insertUsing(
                        $columns,
                        DB::table($sourceTable)
                            ->select($columns)
                            ->where('company_id', $companyId)
                            ->whereBetween($dateColumn, [$from, $to])
                            ->whereBetween('id', [$minId, $maxId]),
                    );
                }, 3);
            }, 'id');
    }

    /** @return array{0:int,1:string} */
    private function copyToColdRecords(
        string $sourceTable,
        int $companyId,
        string $dateColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $month,
    ): array {
        DB::table('analytics_cold_records')
            ->where('company_id', $companyId)
            ->where('source_table', $sourceTable)
            ->where('archive_month', $month)
            ->delete();

        $count = 0;
        $hash = hash_init('sha256');
        DB::table($sourceTable)
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($sourceTable, $companyId, $dateColumn, $month, &$count, $hash): void {
                $payload = [];
                foreach ($rows as $row) {
                    $array = (array) $row;
                    ksort($array);
                    $json = json_encode($array, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $checksum = hash('sha256', $json);
                    hash_update($hash, $json."\n");
                    $payload[] = [
                        'company_id' => $companyId,
                        'source_table' => $sourceTable,
                        'archive_month' => $month,
                        'source_id' => (int) $array['id'],
                        'source_created_at' => $array[$dateColumn] ?? null,
                        'row_checksum' => $checksum,
                        'payload' => $json,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $count++;
                }
                DB::table('analytics_cold_records')->insert($payload);
            });

        return [$count, hash_final($hash)];
    }

    private function checksum(
        string $table,
        int $companyId,
        string $dateColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): string {
        $hash = hash_init('sha256');
        $columns = Schema::getColumnListing($table);
        DB::connection()->disableQueryLog();

        DB::table($table)
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to])
            ->select($columns)
            ->orderBy('id')
            ->chunkById((int) config('reporting.archive.checksum_chunk_size', 1000), function ($rows) use ($hash): void {
                foreach ($rows as $row) {
                    $payload = (array) $row;
                    ksort($payload);
                    hash_update($hash, json_encode(
                        $payload,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    )."\n");
                }
            }, 'id');

        return hash_final($hash);
    }

    private function archiveTableName(string $sourceTable, string $month): string
    {
        if (! preg_match('/^[a-z0-9_]+$/', $sourceTable)) {
            throw new InvalidArgumentException('Unsafe source table name.');
        }

        return $sourceTable.'_'.str_replace('-', '_', $month);
    }

    private function quoteIdentifier(ConnectionInterface $connection, string $identifier): string
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Unsafe SQL identifier.');
        }

        return $connection->getDriverName() === 'mysql' ? "`{$identifier}`" : '"'.$identifier.'"';
    }
}
