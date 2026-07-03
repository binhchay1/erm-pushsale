<?php

namespace Database\Seeders;

use App\Enums\LeadAllocationMode;
use App\Integrations\Landing\LandingFormDriver;
use App\Jobs\Leads\FinalizeLandingLeadJob;
use App\Models\MarketingSource;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Queue;

/**
 * Dữ liệu demo cho LUỒNG LANDING MỚI (gộp đơn + upsale trang cảm ơn + giữ số):
 *
 * 1) Đơn đã gộp: form đầu (combo) + upsale trang cảm ơn của cùng khách → 1 đơn duy nhất,
 *    có địa chỉ (field tiếng Việt có dấu), có dòng combo + dòng upsell.
 * 2) Lead "đang gom": khách vừa submit form đầu trên chiến dịch bật theo dõi phiên JS,
 *    hệ thống GIỮ SỐ chờ upsale → minh hoạ trạng thái Gathering.
 */
class LandingFlowSeeder extends Seeder
{
    public function __construct(
        private readonly LeadIngestionService $leads,
    ) {}

    public function run(): void
    {
        $campaign = MarketingSource::query()
            ->where('ad_channel', 'landing')
            ->where('is_approved', true)
            ->first();

        if (! $campaign) {
            $this->command?->warn('Bỏ qua LandingFlowSeeder: chưa có chiến dịch Landing đã duyệt.');

            return;
        }

        // Chốt & chia số tự động cho phần finalize đơn gộp.
        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);

        // Không để job giữ số tự chạy trong lúc seed (deterministic, tránh vòng lặp
        // re-dispatch khi queue chạy đồng bộ). Seeder tự quyết định lead nào chốt.
        Queue::fake([FinalizeLandingLeadJob::class]);

        $driver = new LandingFormDriver;

        // 1) Đơn gộp: combo (form đầu) + upsale (trang cảm ơn) → 1 đơn.
        $merged = $this->leads->ingestForCampaign($driver, $campaign, [
            'submission_id' => 'demo-landing-merged',
            'name' => 'Nguyễn Thị Thu Hà',
            'phone' => '0987000111',
            'fields' => [
                ['name' => 'Địa chỉ nhận hàng', 'value' => '12 Nguyễn Trãi, Thanh Xuân, Hà Nội'],
            ],
            'combo' => 'Mua 2 Gối mây đan : 289k + Miễn Ship (Bán Chạy)',
            'discount' => '20k',
        ]);

        $this->leads->ingestUpsellForCampaign($driver, $campaign, [
            'submission_id' => 'demo-landing-merged-upsell',
            'phone' => '0987000111',
            'mua_them_1' => 'Mua Thêm 1 Ruột Gối Cao Cấp: 89K',
        ]);

        $this->leads->finalizeGatheringLead($merged->fresh());

        // 2) Lead đang gom (giữ số) — chưa upsale, chưa chốt.
        $this->leads->ingestForCampaign($driver, $campaign, [
            'submission_id' => 'demo-landing-gathering',
            'name' => 'Trần Quang Huy',
            'phone' => '0987000222',
            'fields' => [
                ['name' => 'Địa chỉ', 'value' => '88 Lê Lợi, Hải Châu, Đà Nẵng'],
            ],
            'combo' => 'Mua 1 Gối mây đan : 149k',
        ]);

        $this->command?->info('Đã seed luồng Landing mới: 1 đơn gộp (combo + upsale) + 1 lead đang gom.');
    }
}
