<?php

namespace App\Console\Commands;

use Database\Seeders\FlowDataResetSeeder;
use Illuminate\Console\Command;

final class ClearDemoSeedDataCommand extends Command
{
    protected $signature = 'data:clear-demo-seed {--force : Skip confirmation prompt}';

    protected $description = 'Xóa dữ liệu demo seed (giữ tài khoản và cấu hình thật)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $ok = $this->confirm('Xóa dữ liệu demo seed (giữ users/teams/companies)?', false);
            if (! $ok) {
                $this->warn('Đã hủy.');
                return self::SUCCESS;
            }
        }

        $this->call('db:seed', [
            '--class' => FlowDataResetSeeder::class,
            '--force' => true,
        ]);

        $this->info('Đã dọn dữ liệu demo seed. Tài khoản và cấu hình được giữ nguyên.');
        return self::SUCCESS;
    }
}

