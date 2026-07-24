<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Pushsale\ProductComboItem;
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

        $extraProducts = [
            ['SP-SON-01', 'Son môi thạch hoa NK', 189_000],
            ['SP-DAU-01', 'Dầu gội phủ bạc NEZHA', 229_000],
            ['SP-AU-INOX', 'Ấu inox đựng dầu mỡ', 159_000],
            ['SP-BUT-01', 'Bút phủ bạc', 99_000],
            ['SP-THANH-01', 'Thanh năng lượng mini', 79_000],
            ['SP-PROTEIN-01', 'Thanh protein bar mini', 89_000],
            ['SP-KHAN-01', 'Khăn choàng ren cổ điển', 249_000],
            ['SP-TINHDAU-01', 'Tinh dầu dưỡng tóc Raip NK', 219_000],
            ['SP-SET-01', 'Set 6 đôi khuyên tai mạ bạc NK', 329_000],
            ['SP-HOPCAM-01', 'Hộp cam - Vị cay thơm', 129_000],
            ['SP-HOPDO-01', 'Hộp đỏ - Vị BBQ', 129_000],
            ['SP-HOPXANH-01', 'Hộp xanh - Nguyên vị', 129_000],
            ['SP-NGOCNAU-01', 'Ngọc Nâu', 199_000],
            ['SP-NGOCXANH-01', 'Ngọc Xanh', 199_000],
            ['SP-NGOCTRANG-01', 'Ngọc Trắng', 199_000],
            ['SP-DAILIT-01', 'Dầu xả 1 Lít', 119_000],
            ['SP-DAILIT-03', 'Dầu xả 3 Lít', 289_000],
            ['SP-CARE-01', 'Dail Care pack', 259_000],
            ['SP-TRAVEL-01', 'Travel size pack', 149_000],
            ['SP-FAMILY-01', 'Family combo pack', 499_000],
        ];

        foreach ($extraProducts as [$sku, $name, $price]) {
            Product::query()->create([
                'sku' => $sku,
                'name' => $name,
                'unit_price' => $price,
            ]);
        }

        // Combo bán kèm — hiển thị trong danh mục và cho phép chọn nhanh khi chốt đơn.
        $comboGoi = Product::query()->create([
            'sku' => 'CB-GOI-02',
            'name' => 'Combo 2 Gối mây đan cao cấp',
            'type' => 'combo',
            'unit_price' => 549_000,
        ]);

        $camera = Product::query()->where('sku', 'SP-CAM-01')->first();
        $serum = Product::query()->where('sku', 'SP-SRM-01')->first();
        $botDietCo = Product::query()->where('sku', 'SP-BDC-01')->first();

        $comboSerumCamera = Product::query()->create([
            'sku' => 'CB-SRM-CAM',
            'name' => 'Combo Serum Vitamin C + Camera an ninh',
            'type' => 'combo',
            'unit_price' => 1_150_000,
        ]);

        $comboBotDietCo = Product::query()->create([
            'sku' => 'CB-BDC-03',
            'name' => 'Combo 3 Bột diệt cỏ sinh học',
            'type' => 'combo',
            'unit_price' => 320_000,
        ]);

        foreach ([
            [$comboGoi, $goi, 2],
            [$comboSerumCamera, $serum, 1],
            [$comboSerumCamera, $camera, 1],
            [$comboBotDietCo, $botDietCo, 3],
        ] as [$combo, $component, $quantity]) {
            if (! $combo || ! $component) {
                continue;
            }
            ProductComboItem::query()->create([
                'combo_product_id' => $combo->id,
                'component_product_id' => $component->id,
                'quantity' => $quantity,
                'unit_price' => (int) $component->unit_price,
            ]);
        }

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
