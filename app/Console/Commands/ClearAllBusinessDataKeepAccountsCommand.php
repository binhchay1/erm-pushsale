<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ClearAllBusinessDataKeepAccountsCommand extends Command
{
    protected $signature = 'data:clear-all-keep-accounts {--force : Skip confirmation prompt}';

    protected $description = 'Xoa toan bo du lieu nghiep vu/bao cao, giu lai bang tai khoan de dang nhap test';

    /** @var list<string> */
    private const KEEP_TABLES = [
        'migrations',
        'users',
        'companies',
        'teams',
        'user_operational_profiles',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $ok = $this->confirm('Xoa TOAN BO du lieu nghiep vu/bao cao, chi giu bang tai khoan?', false);
            if (! $ok) {
                $this->warn('Da huy.');
                return self::SUCCESS;
            }
        }

        $tables = collect(Schema::getTables())
            ->map(function ($row): string {
                if (is_array($row)) {
                    return (string) ($row['name'] ?? $row['table_name'] ?? reset($row));
                }

                return (string) $row;
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->values();

        Schema::disableForeignKeyConstraints();
        $cleared = 0;

        foreach ($tables as $table) {
            if (in_array($table, self::KEEP_TABLES, true)) {
                continue;
            }

            DB::table($table)->truncate();
            $cleared++;
            $this->line("Truncated: {$table}");
        }

        Schema::enableForeignKeyConstraints();

        $this->info("Da xoa du lieu {$cleared} bang. Giu lai: ".implode(', ', self::KEEP_TABLES));

        return self::SUCCESS;
    }
}

