<?php

namespace App\Services\Marketing;

use App\Events\DashboardStatsUpdated;
use App\Models\MarketingSource;
use App\Models\User;
use App\Services\DashboardStatsService;

/**
 * Đẩy số liệu dashboard MKT realtime khi có lead mới — không phụ thuộc chia số sale.
 */
final class MarketingStatsBroadcaster
{
    public function broadcastForCampaign(?MarketingSource $campaign): void
    {
        if (! $campaign?->marketer_user_id) {
            return;
        }

        $marketer = User::query()->find($campaign->marketer_user_id);

        if (! $marketer) {
            return;
        }

        try {
            event(new DashboardStatsUpdated(
                'marketing',
                DashboardStatsService::marketingSnapshot($marketer),
            ));
        } catch (\Throwable) {
            // Không chặn luồng nhận lead.
        }
    }

    public function broadcastForMarketer(User $marketer): void
    {
        try {
            event(new DashboardStatsUpdated(
                'marketing',
                DashboardStatsService::marketingSnapshot($marketer),
            ));
        } catch (\Throwable) {
        }
    }
}
