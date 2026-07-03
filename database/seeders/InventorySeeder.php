<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\Inventory\InventoryIntakeService;
use Illuminate\Database\Seeder;

/**
 * Nhập kho ban đầu qua đúng nghiệp vụ: nhân viên kho lập phiếu,
 * trưởng kho ký duyệt → tồn kho và lịch sử nhập xuất luôn khớp nhau.
 */
class InventorySeeder extends Seeder
{
    public function __construct(
        private readonly InventoryIntakeService $intakeService,
    ) {}

    public function run(): void
    {
        // Nhân viên kho lập phiếu, trưởng kho (warehouse@) ký duyệt.
        $staff = User::query()->where('email', 'wh01@saleops.local')->firstOrFail();
        $head = User::query()->where('email', 'warehouse@saleops.local')->firstOrFail();
        $warehouses = Warehouse::query()->orderBy('id')->get();
        $products = Product::query()->orderBy('id')->get();

        // Lượng nhập ban đầu đủ lớn để trừ kho cho toàn bộ đơn demo
        $intakePlan = [
            'SP-GOI-01' => 220,
            'SP-GOI-01S' => 360,
            'SP-CAM-01' => 180,
            'SP-SRM-01' => 260,
            'SP-BDC-01' => 300,
        ];

        foreach ($warehouses as $wIndex => $warehouse) {
            foreach ($products as $pIndex => $product) {
                $quantity = $intakePlan[$product->sku] ?? 200;

                $movement = $this->intakeService->intake(
                    $warehouse->id,
                    $product->id,
                    $quantity,
                    $staff,
                    'Nhập lô hàng đầu kỳ từ nhà cung cấp',
                    $head->id,
                );

                // Lùi ngày phiếu nhập về đầu kỳ để lịch sử trông tự nhiên
                $movement->forceFill([
                    'created_at' => now()->subDays(45)->setTime(8, 30)->addMinutes($wIndex * 60 + $pIndex * 7),
                    'updated_at' => now()->subDays(45),
                ])->save();

                WarehouseInventory::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->update(['location_code' => chr(65 + $pIndex).'-0'.($wIndex + 1)]);
            }
        }

        // Một vài phiếu xuất thủ công (chuyển mẫu, hủy hàng lỗi) để có dữ liệu 2 chiều
        $hn = $warehouses->firstWhere('code', 'HN') ?? $warehouses->first();
        $exports = [
            ['SP-CAM-01', 5, 'Xuất hàng mẫu cho đội Marketing quay quảng cáo', 30],
            ['SP-SRM-01', 12, 'Hủy lô hàng lỗi bao bì theo biên bản kiểm kê', 18],
            ['SP-GOI-01S', 8, 'Chuyển hàng trưng bày showroom', 9],
        ];

        foreach ($exports as [$sku, $qty, $note, $daysAgo]) {
            $product = $products->firstWhere('sku', $sku);

            if (! $product) {
                continue;
            }

            $movement = $this->intakeService->export($hn->id, $product->id, $qty, $staff, $note, $head->id);
            $movement->forceFill([
                'created_at' => now()->subDays($daysAgo)->setTime(15, 0),
                'updated_at' => now()->subDays($daysAgo),
            ])->save();
        }

        $this->command?->info('Đã nhập kho đầu kỳ ('.WarehouseInventoryMovement::query()->count().' phiếu có người duyệt).');
    }
}
