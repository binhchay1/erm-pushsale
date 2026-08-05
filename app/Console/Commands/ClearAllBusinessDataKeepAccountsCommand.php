<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ClearAllBusinessDataKeepAccountsCommand extends Command
{
    /**
     * Bảng phải giữ để toàn bộ tài khoản hiện có vẫn đăng nhập được sau khi xóa dữ liệu nghiệp vụ.
     * `companies` và `teams` được giữ cùng `users` vì user đang tham chiếu company/team.
     */
    private const ACCOUNT_TABLES = [
        'companies',
        'teams',
        'users',
        'user_preferences',
        'personal_access_tokens',
        'password_reset_tokens',
        'sessions',
    ];

    private const SYSTEM_TABLES = [
        'migrations',
        'sqlite_sequence',
    ];

    protected $signature = 'data:clear-all-keep-accounts
        {--force : Bỏ qua câu hỏi xác nhận}
        {--dry-run : Chỉ hiển thị danh sách bảng sẽ xóa, không xóa dữ liệu}
        {--flush-sessions : Xóa cả bảng sessions để đăng xuất toàn bộ phiên hiện tại}';

    protected $description = 'Xóa toàn bộ dữ liệu nghiệp vụ, giữ nguyên toàn bộ tài khoản/company/team hiện có';

    public function handle(): int
    {
        $tables = $this->tableNames();
        $preservedTables = $this->preservedTables($tables);
        $tablesToClear = $tables
            ->reject(fn (string $table): bool => $preservedTables->contains($table))
            ->values();

        $this->info('Các bảng sẽ GIỮ lại: '.$preservedTables->implode(', '));

        if ($tablesToClear->isEmpty()) {
            $this->warn('Không có bảng nào cần xóa.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn('Các bảng sẽ XÓA dữ liệu:');
        $this->table(
            ['#', 'table', 'rows'],
            $tablesToClear->values()->map(fn (string $table, int $index): array => [
                $index + 1,
                $table,
                $this->safeCount($table),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry-run: chưa xóa dữ liệu.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'Lệnh này sẽ XÓA SẠCH dữ liệu nghiệp vụ nhưng giữ nguyên toàn bộ tài khoản hiện có. Tiếp tục?',
                false,
            );

            if (! $confirmed) {
                $this->warn('Đã hủy.');

                return self::SUCCESS;
            }
        }

        $cleared = 0;

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tablesToClear as $table) {
                $this->clearTable($table);
                $cleared++;
                $this->line("Cleared: {$table}");
            }
        } catch (Throwable $exception) {
            $this->error('Xóa dữ liệu bị lỗi tại bảng đang xử lý.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info("Đã xóa dữ liệu {$cleared} bảng, giữ nguyên tài khoản/company/team hiện có.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, string>
     */
    private function tableNames(): Collection
    {
        return collect(Schema::getTables())
            ->filter(function (mixed $row): bool {
                if (! is_array($row)) {
                    return true;
                }

                $type = strtolower((string) ($row['type'] ?? 'table'));

                return $type === 'table' || $type === 'base table';
            })
            ->map(function (mixed $row): string {
                if (is_array($row)) {
                    return (string) ($row['name'] ?? $row['table_name'] ?? reset($row));
                }

                return (string) $row;
            })
            ->filter(fn (string $table): bool => $table !== '')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $tables
     * @return Collection<int, string>
     */
    private function preservedTables(Collection $tables): Collection
    {
        $preserved = collect(self::SYSTEM_TABLES)
            ->merge(self::ACCOUNT_TABLES);

        if ($this->option('flush-sessions')) {
            $preserved = $preserved->reject(fn (string $table): bool => $table === 'sessions');
        }

        return $preserved
            ->filter(fn (string $table): bool => $tables->contains($table))
            ->unique()
            ->sort()
            ->values();
    }

    private function clearTable(string $table): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::table($table)->delete();

            try {
                DB::statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
            } catch (Throwable) {
                // Bảng sqlite_sequence không tồn tại cho database chưa có AUTOINCREMENT.
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('TRUNCATE TABLE '.$this->wrappedTable($table).' RESTART IDENTITY CASCADE');

            return;
        }

        DB::table($table)->truncate();
    }

    private function wrappedTable(string $table): string
    {
        return DB::connection()->getQueryGrammar()->wrapTable($table);
    }

    private function safeCount(string $table): string
    {
        try {
            return (string) DB::table($table)->count();
        } catch (Throwable) {
            return '?';
        }
    }
}
