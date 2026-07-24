<?php

namespace Database\Seeders;

use App\Models\FailedPartnerOrder;
use App\Models\MarketingSource;
use App\Models\Pushsale\EcommerceShopConnection;
use App\Models\Warehouse;
use App\Services\Ecommerce\EcommerceSyncService;
use Illuminate\Database\Seeder;

class EcommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = Warehouse::query()->orderBy('id')->get();
        if ($warehouses->isEmpty()) {
            return;
        }

        $sources = MarketingSource::query()->orderBy('id')->limit(6)->get();
        $seedRows = [
            ['tiktok', 'TK-HB-001', 'TikTok HB Official', 'Kênh chính TikTok Shop'],
            ['tiktok', 'TK-HN-002', 'TikTok Hà Nội Flash Sale', 'Shop phụ chạy flash sale'],
            ['shopee', 'SP-HB-001', 'Shopee HB Mall', 'Shop Shopee đồng bộ đơn hàng'],
            ['shopee', 'SP-HN-002', 'Shopee Hà Nội Care', 'Shop Shopee phụ'],
        ];

        $sync = app(EcommerceSyncService::class);

        foreach ($seedRows as $index => [$platform, $shopId, $shopName, $note]) {
            $warehouse = $warehouses[$index % $warehouses->count()];
            $source = $sources->isNotEmpty() ? $sources[$index % $sources->count()] : null;
            $shop = EcommerceShopConnection::query()->updateOrCreate(
                ['platform' => $platform, 'shop_id' => $shopId],
                [
                    'warehouse_id' => $warehouse->id,
                    'marketing_source_id' => $source?->id,
                    'shop_name' => $shopName,
                    'logo_url' => 'https://dummyimage.com/80x80/3b86d9/ffffff&text='.strtoupper(substr($platform, 0, 2)),
                    'note' => $note,
                    'logistics_mode' => 0,
                    'is_enabled' => true,
                    'last_synced_at' => now()->subMinutes(15 + $index),
                ]
            );

            $sync->syncProducts($shop);
            $sync->fetchMissingOrders($shop);
        }

        FailedPartnerOrder::query()->firstOrCreate(
            ['platform' => 'TikTok', 'partner_order_id' => 'TIKTOK-DEMO-MISSING-PHONE'],
            [
                'warehouse_id' => $warehouses->first()->id,
                'shop_name' => 'TikTok HB Official',
                'error_description' => 'Đơn từ TikTok thiếu số điện thoại nên chưa thể tạo đơn Pushsale.',
            ]
        );

        $this->command?->info('Đã tạo dữ liệu demo TMĐT: shop, sản phẩm liên kết và đơn lỗi.');
    }
}
