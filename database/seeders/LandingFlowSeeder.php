<?php

namespace Database\Seeders;

use App\Enums\LeadAllocationMode;
use App\Integrations\Landing\LandingFormDriver;
use App\Jobs\Leads\FinalizeLandingLeadJob;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Queue;

/**
 * Dữ liệu demo cho LUỒNG LANDING (gộp đơn + upsale trang cảm ơn):
 *
 * 1) Đơn đã gộp: form đầu (combo) + upsale trang cảm ơn → 1 đơn duy nhất.
 * 2) Đơn đang chờ upsale: form đầu đã chia số, badge "chờ upsale" trên tác nghiệp.
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

        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);

        Queue::fake([FinalizeLandingLeadJob::class]);

        $driver = new LandingFormDriver;

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

        $mergedOrder = Order::query()->whereKey($merged->fresh()->order_id)->first();
        if ($mergedOrder) {
            $this->leads->releaseLandingUpsellHold($merged->fresh());
        }

        $this->leads->ingestForCampaign($driver, $campaign, [
            'submission_id' => 'demo-landing-awaiting-upsell',
            'name' => 'Trần Quang Huy',
            'phone' => '0987000222',
            'fields' => [
                ['name' => 'Địa chỉ', 'value' => '88 Lê Lợi, Hải Châu, Đà Nẵng'],
            ],
            'combo' => 'Mua 1 Gối mây đan : 149k',
        ]);

        $this->command?->info('Đã seed luồng Landing: 1 đơn gộp (combo + upsale) + 1 đơn đang chờ upsale.');
    }
}
