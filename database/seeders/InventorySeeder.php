<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WarehouseVoucherLine;
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
                $timestamp = now()->subDays(45)->setTime(8, 30)->addMinutes($wIndex * 60 + $pIndex * 7);
                $movement->forceFill([
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->save();
                $this->attachVoucher($movement, 'inbound', 'PNK', $staff, $head, $timestamp);

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
            $timestamp = now()->subDays($daysAgo)->setTime(15, 0);
            $movement->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();
            $this->attachVoucher($movement, 'outbound', 'PXK', $staff, $head, $timestamp);
        }

        $this->command?->info('Đã nhập kho đầu kỳ ('.WarehouseInventoryMovement::query()->count().' phiếu có người duyệt).');
    }
    private function attachVoucher(WarehouseInventoryMovement $movement, string $type, string $prefix, User $staff, User $head, $timestamp): void
    {
        $code = $prefix.'-'.$timestamp->format('Ymd').'-'.$movement->id;

        $voucher = WarehouseVoucher::query()->create([
            'warehouse_id' => $movement->warehouse_id,
            'code' => $code,
            'type' => $type,
            'document_date' => $timestamp->toDateString(),
            'note' => $movement->note,
            'status' => 'confirmed',
            'approved_by_user_id' => $head->id,
            'created_by_user_id' => $staff->id,
            'updated_by_user_id' => $staff->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        WarehouseVoucherLine::query()->create([
            'warehouse_voucher_id' => $voucher->id,
            'product_id' => $movement->product_id,
            'document_quantity' => abs((int) $movement->quantity),
            'quantity' => abs((int) $movement->quantity),
            'unit_cost' => (int) $movement->unit_cost,
            'location_code' => $movement->inventory?->location_code,
            'note' => $movement->note,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $movement->forceFill([
            'reference_type' => 'warehouse_voucher',
            'reference_id' => $voucher->id,
        ])->save();
    }

}
