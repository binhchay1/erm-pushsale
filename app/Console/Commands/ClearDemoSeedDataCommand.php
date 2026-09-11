<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ClearDemoSeedDataCommand extends Command
{
    protected $signature = 'data:clear-demo-seed {--force : Skip confirmation prompt}';

    protected $description = 'Xoa toan bo du lieu — chi giu tai khoan superadmin';

    public function handle(): int
    {
        return $this->call('data:clear-all-keep-accounts', [
            '--force' => $this->option('force'),
            '--only-superadmin' => true,
            '--flush-sessions' => true,
        ]);
    }
}
