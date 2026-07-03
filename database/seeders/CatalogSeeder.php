<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Danh mục sản phẩm (cha + biến thể) và hệ thống kho.
 * Admin là người duy nhất quản lý danh mục này trên giao diện.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $goi = Product::query()->create([
            'sku' => 'SP-GOI-01',
            'name' => 'Gối mây đan cao cấp',
            'unit_price' => 299_000,
        ]);

        Product::query()->create([
            'parent_id' => $goi->id,
            'sku' => 'SP-GOI-01S',
            'name' => 'Gối mây đan — size nhỏ',
            'unit_price' => 159_000,
        ]);

        Product::query()->create([
            'sku' => 'SP-CAM-01',
            'name' => 'Camera mini an ninh',
            'unit_price' => 890_000,
        ]);

        Product::query()->create([
            'sku' => 'SP-SRM-01',
            'name' => 'Serum dưỡng da Vitamin C',
            'unit_price' => 350_000,
        ]);

        Product::query()->create([
            'sku' => 'SP-BDC-01',
            'name' => 'Bột diệt cỏ sinh học',
            'unit_price' => 120_000,
        ]);

        // Combo bán kèm — hiển thị trong danh mục và cho phép chọn nhanh khi chốt đơn.
        Product::query()->create([
            'sku' => 'CB-GOI-02',
            'name' => 'Combo 2 Gối mây đan cao cấp',
            'type' => 'combo',
            'unit_price' => 549_000,
        ]);

        Product::query()->create([
            'sku' => 'CB-SRM-CAM',
            'name' => 'Combo Serum Vitamin C + Camera an ninh',
            'type' => 'combo',
            'unit_price' => 1_150_000,
        ]);

        Product::query()->create([
            'sku' => 'CB-BDC-03',
            'name' => 'Combo 3 Bột diệt cỏ sinh học',
            'type' => 'combo',
            'unit_price' => 320_000,
        ]);

        $warehouseHead = User::query()->where('email', 'warehouse@saleops.local')->first();

        Warehouse::query()->create([
            'code' => 'HN',
            'name' => 'Kho Hà Nội',
            'phone' => '0988111222',
            'address' => 'KCN Quang Minh, Mê Linh, Hà Nội',
            'manager_user_id' => $warehouseHead?->id,
            'vtp_code' => 'VTP-HN-01',
        ]);

        Warehouse::query()->create([
            'code' => 'HCM',
            'name' => 'Kho Hồ Chí Minh',
            'phone' => '0988333444',
            'address' => 'KCN Tân Bình, Q. Tân Phú, TP.HCM',
            'manager_user_id' => $warehouseHead?->id,
            'vtp_code' => 'VTP-HCM-01',
        ]);

        $this->command?->info('Đã tạo danh mục sản phẩm và 2 kho.');
    }
}
