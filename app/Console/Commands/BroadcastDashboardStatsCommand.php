<?php

namespace App\Console\Commands;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Events\DashboardStatsUpdated;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Console\Command;

class BroadcastDashboardStatsCommand extends Command
{
    protected $signature = 'dashboard:broadcast {--once : Chỉ broadcast một lần}';

    protected $description = 'Phát số liệu dashboard real-time qua Reverb';

    public function handle(): int
    {
        $this->info('Đang phát dashboard stats… (Ctrl+C để dừng)');

        $adminUser = User::query()->where('role', UserRole::Admin)->orderBy('id')->first();
        $adminFilter = new ReportFilterData;

        do {
            if ($adminUser) {
                event(new DashboardStatsUpdated(
                    'admin',
                    DashboardStatsService::adminSnapshot($adminUser, $adminFilter),
                ));
            }

            $salesUser = User::query()->where('email', 'sales@saleops.local')->first();
            if ($salesUser) {
                event(new DashboardStatsUpdated(
                    'sales',
                    DashboardStatsService::salesSnapshot($salesUser, new ReportFilterData),
                ));
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
