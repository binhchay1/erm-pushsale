<?php

namespace App\Console\Commands;

use App\Events\DashboardStatsUpdated;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Console\Command;

class BroadcastDashboardStatsCommand extends Command
{
    protected $signature = 'dashboard:broadcast {--once : Chỉ broadcast một lần}';

    protected $description = 'Phát số liệu dashboard real-time qua Reverb (demo)';

    public function handle(): int
    {
        $this->info('Đang phát dashboard stats… (Ctrl+C để dừng)');

        do {
            event(new DashboardStatsUpdated('admin', DashboardStatsService::adminSnapshot()));

            $salesUser = User::query()->where('email', 'sales@saleops.local')->first();
            if ($salesUser) {
                event(new DashboardStatsUpdated('sales', DashboardStatsService::salesSnapshot($salesUser)));
            }

            $this->line('['.now()->format('H:i:s').'] Broadcast admin + sales');

            if ($this->option('once')) {
                break;
            }

            sleep(4);
        } while (true);

        return self::SUCCESS;
    }
}
