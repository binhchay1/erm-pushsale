<?php

namespace Database\Seeders;

use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Marketing\CampaignLandingService;
use Illuminate\Database\Seeder;

/**
 * Chiến dịch quảng cáo do nhân viên Marketing tạo, admin duyệt.
 * Mỗi chiến dịch gắn 1 sản phẩm + 1 marketer phụ trách — đơn hàng
 * sinh ra từ chiến dịch sẽ tính doanh số cho đúng người, đúng team.
 */
class MarketingCampaignSeeder extends Seeder
{
    public function __construct(
        private readonly CampaignLandingService $landing,
    ) {}

    public function run(): void
    {
        $marketers = User::query()
            ->whereIn('email', [
                'marketing@saleops.local',
                'mkt02@saleops.local',
                'mkt03@saleops.local',
                'mkt05@saleops.local',
                'mkt06@saleops.local',
                'mkt07@saleops.local',
            ])
            ->orderBy('id')
            ->get()
            ->keyBy('email');

        $products = Product::query()->get()->keyBy('sku');

        $campaigns = [
            // [tên, sku, email marketer, kênh, utm_source, ngân sách, tương tác, liên hệ, đã duyệt]
            ['Gối mây đan — Facebook Ads', 'SP-GOI-01S', 'marketing@saleops.local', 'facebook', 'facebook', 15_000_000, 4200, 520, true],
            ['Camera mini an ninh — Facebook', 'SP-CAM-01', 'mkt02@saleops.local', 'facebook', 'facebook', 12_000_000, 3100, 380, true],
            ['Serum Vitamin C — TikTok Shop', 'SP-SRM-01', 'mkt03@saleops.local', 'tiktok', 'tiktok', 9_000_000, 5600, 430, true],
            ['Bột diệt cỏ — Google Search', 'SP-BDC-01', 'mkt05@saleops.local', 'google', 'google', 7_500_000, 1900, 260, true],
            ['Gối mây đan — Landing Ladipage', 'SP-GOI-01', 'mkt06@saleops.local', 'landing', 'ladipage', 6_000_000, 1400, 190, true],
            // 1 chiến dịch chờ admin duyệt — lead về sẽ nằm ở hàng chờ chia số
            ['Serum Vitamin C — Zalo Ads', 'SP-SRM-01', 'mkt07@saleops.local', 'zalo', 'zalo', 4_000_000, 600, 75, false],
        ];

        foreach ($campaigns as [$name, $sku, $email, $channel, $utmSource, $budget, $interactions, $contacts, $approved]) {
            $marketer = $marketers->get($email);

            MarketingSource::query()->create([
                'name' => $name,
                'product_id' => $products->get($sku)?->id,
                'marketer_user_id' => $marketer?->id,
                'created_by_user_id' => $marketer?->id,
                'ad_channel' => $channel,
                'utm_source' => $utmSource,
                'utm_campaign' => $this->landing->utmCampaignFromName($name),
                'webhook_token' => $this->landing->generateToken(),
                'budget' => $budget,
                'interactions' => $interactions,
                'contacts' => $contacts,
                'is_active' => true,
                'is_approved' => $approved,
            ]);
        }

        $this->command?->info('Đã tạo '.count($campaigns).' chiến dịch marketing (1 chiến dịch chờ duyệt).');
    }
}
