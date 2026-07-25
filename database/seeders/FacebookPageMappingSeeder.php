<?php

namespace Database\Seeders;

use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use App\Models\MarketingSource;
use App\Models\Pushsale\FacebookPageMapping;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FacebookPageMappingSeeder extends Seeder
{
    public function run(): void
    {
        $marketers = User::query()
            ->where('role', User::ROLE_MARKETING)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        if ($marketers->isEmpty()) {
            $this->command?->warn('Không có tài khoản marketing để seed cấu hình Facebook.');
            return;
        }

        $pages = [
            ['102938475610001', 'TT Group Official', 'Meta Business Admin', 0],
            ['102938475610002', 'SaleLoop Mỹ phẩm', 'Lê Hoàng Anh', 1],
            ['102938475610003', 'SaleLoop Gia dụng', 'Đỗ Mai Phương', 2],
            ['102938475610004', 'SaleLoop Camera an ninh', 'Ngô Văn Sơn', 3],
            ['102938475610005', 'SaleLoop Combo ưu đãi', 'Bùi Thị Ngọc', 4],
        ];

        foreach ($pages as [$pageId, $pageName, $creator, $marketerIndex]) {
            $marketer = $marketers[$marketerIndex % $marketers->count()];

            FacebookPageMapping::query()->updateOrCreate(
                ['page_id' => $pageId],
                [
                    'page_name' => $pageName,
                    'creator_name' => $creator,
                    'marketer_user_id' => $marketer->id,
                    'is_active' => true,
                    'metadata' => [
                        'source' => 'demo_seed',
                        'webhook_hint' => '/api/v1/webhooks/facebook',
                    ],
                ],
            );

            MarketingSource::query()->updateOrCreate(
                ['utm_source' => 'facebook', 'utm_campaign' => $pageId],
                [
                    'name' => 'Facebook — '.$pageName,
                    'marketer_user_id' => $marketer->id,
                    'ad_channel' => 'Facebook',
                    'budget' => 5_000_000 + ($marketerIndex * 1_500_000),
                    'interactions' => 1200 + ($marketerIndex * 240),
                    'contacts' => 40 + ($marketerIndex * 8),
                    'is_active' => true,
                    'is_approved' => true,
                ],
            );
        }

        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Facebook);
        $payload = [
            'is_enabled' => true,
            'verify_token' => $connection->verify_token ?: 'sale-loop-facebook-demo',
            'webhook_secret' => $connection->webhook_secret ?: Str::random(32),
        ];

        // Một số DB staging đã chạy migration cleanup cũ làm mất cột metadata.
        // Seeder phải chạy được cả trước và sau khi migrate bản restore mới, tránh chết giữa chừng khi QA dữ liệu.
        if (Schema::hasColumn('integration_connections', 'metadata')) {
            $metadata = is_array($connection->metadata ?? null) ? $connection->metadata : [];
            $payload['metadata'] = array_merge($metadata, [
                'configured_from' => 'menu_1_11_seed',
                'page_mapping_count' => count($pages),
            ]);
        }

        $connection->forceFill($payload)->save();

        $this->command?->info('Đã tạo cấu hình Facebook đơn vị mẫu cho '.count($pages).' Fanpage.');
    }
}
