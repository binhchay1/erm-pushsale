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
    /**
     * Archive one company period.
     *
     * Period formats:
     * - yearly driver (default): YYYY  → table source_YYYY, range Jan 1..Dec 31
     * - monthly driver: YYYY-MM → table source_YYYY_MM
     *
     * @return array<string,mixed>
     */
    public function archiveCompanyMonth(
        int $companyId,
        string $period,
        bool $purge = false,
        bool $dryRun = false,
    ): array {
        [$normalized, $from, $to] = $this->resolvePeriod($period);
        $results = [];

        foreach (config('reporting.archive.sources', []) as $sourceTable => $definition) {
            if (! Schema::hasTable($sourceTable)) {
                $results[$sourceTable] = ['status' => 'skipped', 'reason' => 'source_table_missing'];
                continue;
            }

            $lock = Cache::lock("reporting:archive:{$companyId}:{$sourceTable}:{$normalized}", 7200);
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
                    $normalized,
                    $purge && (bool) ($definition['purge_safe'] ?? false),
                    $dryRun,
                );
            } finally {
                $lock->release();
            }
        }

        return $results;
    }

    /** Default period for CLI/schedule based on archive driver. */
    public function defaultPeriod(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now(config('reporting.timezone'));

        return $this->isYearlyDriver()
            ? $now->subYear()->format('Y')
            : $now->subMonth()->format('Y-m');
    }

    public function periodKeyForDate(CarbonImmutable|string $date): string
    {
        $parsed = $date instanceof CarbonImmutable
            ? $date
            : CarbonImmutable::parse($date, config('reporting.timezone'));

        return $this->isYearlyDriver()
            ? $parsed->format('Y')
            : $parsed->format('Y-m');
    }

    public function isYearlyDriver(): bool
    {
        return config('reporting.archive.driver') === 'yearly_tables';
    }

    /**
     * @return array{0:string,1:CarbonImmutable,2:CarbonImmutable}
     */
    private function resolvePeriod(string $period): array
    {
        $period = trim($period);

        if (preg_match('/^\d{4}$/', $period) === 1) {
            $year = CarbonImmutable::createFromFormat('!Y', $period, config('reporting.timezone'));
            if (! $year || $year->format('Y') !== $period) {
                throw new InvalidArgumentException('Year must use YYYY format.');
            }

            return [$period, $year->startOfYear(), $year->endOfYear()->endOfDay()];
        }

        if (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $period, config('reporting.timezone'));
            if (! $month || $month->format('Y-m') !== $period) {
                throw new InvalidArgumentException('Month must use YYYY-MM format.');
            }

            return [$period, $month->startOfMonth(), $month->endOfMonth()->endOfDay()];
        }

        throw new InvalidArgumentException('Period must be YYYY (yearly) or YYYY-MM (monthly).');
    }

    /** @return array<string,mixed> */
    private function archiveTable(
        int $companyId,
        string $sourceTable,
        string $dateColumn,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $period,
        bool $purge,
        bool $dryRun,
    ): array {
        $archiveTable = $this->archiveTableName($sourceTable, $period);
        $sourceQuery = DB::table($sourceTable)
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to]);
        $sourceRows = (clone $sourceQuery)->count();
        $sourceChecksum = $this->checksum($sourceTable, $companyId, $dateColumn, $from, $to);

        $manifest = AnalyticsArchiveManifest::query()->updateOrCreate(
            ['company_id' => $companyId, 'source_table' => $sourceTable, 'archive_month' => $period],
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
            if ($this->supportsPhysicalTables()) {
                $this->createArchiveTable($sourceTable, $archiveTable);
                $this->copyToArchiveTable($sourceTable, $archiveTable, $companyId, $dateColumn, $from, $to);
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
                    $period,
                );
                $archiveTable = 'analytics_cold_records';
            }

            // Re-read source after copy. A row may have arrived or changed while chunks were copied.
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

    private function supportsPhysicalTables(): bool
    {
        $driver = (string) config('reporting.archive.driver');
        if (! in_array($driver, ['yearly_tables', 'monthly_tables'], true)) {
            return false;
        }

        return in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true);
    }

    private function createArchiveTable(string $sourceTable, string $archiveTable): void
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

    private function copyToArchiveTable(
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

        // Copy theo dải ID — transaction nhỏ, không giữ lock cả năm.
        DB::table($sourceTable)
            ->select('id')
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to])
            ->orderBy('id')
            ->chunkById((int) config('reporting.archive.copy_chunk_size', 5000), function ($ids) use (
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
        string $period,
    ): array {
        DB::table('analytics_cold_records')
            ->where('company_id', $companyId)
            ->where('source_table', $sourceTable)
            ->where('archive_month', $period)
            ->delete();

        $count = 0;
        $hash = hash_init('sha256');
        DB::table($sourceTable)
            ->where('company_id', $companyId)
            ->whereBetween($dateColumn, [$from, $to])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($sourceTable, $companyId, $dateColumn, $period, &$count, $hash): void {
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
                        'archive_month' => $period,
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
            ->chunkById((int) config('reporting.archive.checksum_chunk_size', 2000), function ($rows) use ($hash): void {
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

    private function archiveTableName(string $sourceTable, string $period): string
    {
        if (! preg_match('/^[a-z0-9_]+$/', $sourceTable)) {
            throw new InvalidArgumentException('Unsafe source table name.');
        }

        // yearly: orders_2025 ; monthly: orders_2025_06
        return $sourceTable.'_'.str_replace('-', '_', $period);
    }

    private function quoteIdentifier(ConnectionInterface $connection, string $identifier): string
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Unsafe SQL identifier.');
        }

        return $connection->getDriverName() === 'mysql' ? "`{$identifier}`" : '"'.$identifier.'"';
    }
}
