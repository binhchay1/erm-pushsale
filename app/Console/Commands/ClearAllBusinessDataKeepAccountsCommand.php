<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ClearAllBusinessDataKeepAccountsCommand extends Command
{
    /**
     * Bảng phải giữ để tài khoản còn đăng nhập được sau khi xóa dữ liệu nghiệp vụ.
     * `companies` và `teams` được giữ cùng `users` vì user đang tham chiếu company/team
     * (trừ khi --only-superadmin: teams bị xóa, companies giữ tối thiểu).
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
        {--flush-sessions : Xóa cả bảng sessions để đăng xuất toàn bộ phiên hiện tại}
        {--only-superadmin : Xóa hết user khác, chỉ giữ tài khoản có is_platform_admin=1 (superadmin)}';

    protected $description = 'Xóa toàn bộ dữ liệu nghiệp vụ; mặc định giữ tài khoản, hoặc --only-superadmin chỉ giữ superadmin';

    public function handle(): int
    {
        $onlySuperAdmin = (bool) $this->option('only-superadmin');
        $tables = $this->tableNames();
        $preservedTables = $this->preservedTables($tables, $onlySuperAdmin);
        $tablesToClear = $tables
            ->reject(fn (string $table): bool => $preservedTables->contains($table))
            ->values();

        $this->info('Các bảng sẽ GIỮ lại: '.$preservedTables->implode(', '));
        if ($onlySuperAdmin) {
            $this->warn('Chế độ --only-superadmin: sau khi xóa bảng, chỉ giữ user is_platform_admin=1.');
        }

        if ($tablesToClear->isEmpty() && ! $onlySuperAdmin) {
            $this->warn('Không có bảng nào cần xóa.');

            return self::SUCCESS;
        }

        if ($tablesToClear->isNotEmpty()) {
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
        }

        if ($this->option('dry-run')) {
            if ($onlySuperAdmin) {
                $keepers = User::query()->where('is_platform_admin', true)->pluck('email')->all();
                $this->info('Dry-run superadmin keep: '.(implode(', ', $keepers) ?: '(không tìm thấy)'));
            }
            $this->info('Dry-run: chưa xóa dữ liệu.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $message = $onlySuperAdmin
                ? 'Lệnh này sẽ XÓA SẠCH dữ liệu nghiệp vụ và CHỈ GIỮ tài khoản superadmin. Tiếp tục?'
                : 'Lệnh này sẽ XÓA SẠCH dữ liệu nghiệp vụ nhưng giữ nguyên toàn bộ tài khoản hiện có. Tiếp tục?';
            $confirmed = $this->confirm($message, false);

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

            if ($onlySuperAdmin) {
                $this->retainOnlySuperAdmins();
            }
        } catch (Throwable $exception) {
            $this->error('Xóa dữ liệu bị lỗi tại bước đang xử lý.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($onlySuperAdmin) {
            $emails = User::query()->pluck('email')->all();
            $this->info("Đã xóa dữ liệu {$cleared} bảng; user còn lại: ".(implode(', ', $emails) ?: '(không còn user)'));
        } else {
            $this->info("Đã xóa dữ liệu {$cleared} bảng, giữ nguyên tài khoản/company/team hiện có.");
        }

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
    private function preservedTables(Collection $tables, bool $onlySuperAdmin): Collection
    {
        $preserved = collect(self::SYSTEM_TABLES)
            ->merge(self::ACCOUNT_TABLES);

        if ($onlySuperAdmin) {
            // Teams demo/ops sẽ trống; xóa luôn để “như ban đầu”.
            $preserved = $preserved->reject(fn (string $table): bool => $table === 'teams');
        }

        if ($this->option('flush-sessions')) {
            $preserved = $preserved->reject(fn (string $table): bool => $table === 'sessions');
        }

        return $preserved
            ->filter(fn (string $table): bool => $tables->contains($table))
            ->unique()
            ->sort()
            ->values();
    }

    private function retainOnlySuperAdmins(): void
    {
        $keeperIds = User::query()
            ->where('is_platform_admin', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($keeperIds === []) {
            throw new \RuntimeException('Không tìm thấy user is_platform_admin=1. Dừng lại để tránh xóa hết tài khoản.');
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereNotIn('tokenable_id', $keeperIds)
                ->delete();
        }

        if (Schema::hasTable('user_preferences')) {
            DB::table('user_preferences')->whereNotIn('user_id', $keeperIds)->delete();
        }

        if (Schema::hasTable('sessions') && ! $this->option('flush-sessions')) {
            // Giữ session của superadmin nếu cột user_id tồn tại.
            if (Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->whereNotNull('user_id')->whereNotIn('user_id', $keeperIds)->delete();
            }
        }

        if (Schema::hasTable('password_reset_tokens')) {
            $emails = User::query()->whereIn('id', $keeperIds)->pluck('email')->all();
            DB::table('password_reset_tokens')->whereNotIn('email', $emails)->delete();
        }

        User::query()->whereNotIn('id', $keeperIds)->delete();

        // Gỡ liên kết team / manager còn sót trên superadmin.
        User::query()->whereIn('id', $keeperIds)->update([
            'team_id' => null,
            'manager_user_id' => null,
            'created_by_user_id' => null,
            'default_shop_id' => null,
        ]);

        if (Schema::hasTable('teams')) {
            $this->clearTable('teams');
        }

        $this->line('Retained superadmin user ids: '.implode(', ', $keeperIds));
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
