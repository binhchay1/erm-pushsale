<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ClearDemoSeedDataCommand extends Command
{
    protected $signature = 'data:clear-demo-seed {--force : Skip confirmation prompt}';

    protected $description = 'Xoa toan bo du lieu demo — chi giu tai khoan superadmin (alias cua data:clear-all-keep-accounts)';

    public function handle(): int
    {
        return $this->call('data:clear-all-keep-accounts', [
            '--force' => $this->option('force'),
        ]);
    }
}
