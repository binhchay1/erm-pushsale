<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Bộ dữ liệu demo đồng bộ toàn hệ thống.
 *
 * Chạy `php artisan db:seed --force` (kể cả trên production/AWS) sẽ:
 * 1. Xóa sạch dữ liệu nghiệp vụ + tài khoản cũ (giữ cấu hình kết nối).
 * 2. Seed lại từ đầu theo đúng business: tài khoản & phân quyền →
 *    danh mục & kho → nhập kho có ký duyệt → chiến dịch marketing →
 *    lead & đơn hàng chạy qua đúng service nghiệp vụ → đối soát → thông báo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoResetSeeder::class,
            AccountSeeder::class,
            CatalogSeeder::class,
            InventorySeeder::class,
            MarketingCampaignSeeder::class,
            SalesPipelineSeeder::class,
            ShippingEventSeeder::class,
            DemoNotificationSeeder::class,
        ]);
    }
}
