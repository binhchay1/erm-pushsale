<?php

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WarehouseVoucherLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\Pushsale\PushsalePageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WarehouseVoucherBusinessLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_inbound_voucher_creates_line_inventory_and_linked_movement(): void
    {
        $actor = $this->adminUser();
        $warehouse = Warehouse::query()->create(['name' => 'Kho kiểm thử', 'code' => 'TEST']);
        $product = Product::query()->create([
            'name' => 'Sản phẩm kho',
            'sku' => 'KHO-001',
            'unit' => 'chai',
            'unit_price' => 199_000,
            'cost_price' => 80_000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PNK-TEST-001',
                'type' => 'inbound',
                'document_date' => '2026-07-23',
                'product_id' => $product->id,
                'document_quantity' => 12,
                'quantity' => 12,
                'unit_cost' => 81_000,
                'batch_code' => 'LO-001',
                'location_code' => 'A-01',
                'note' => 'Nhập kho kiểm thử',
            ],
        ]);

        $response->assertCreated()->assertJsonPath('ok', true);

        $voucher = WarehouseVoucher::query()->where('code', 'PNK-TEST-001')->firstOrFail();
        $this->assertSame('confirmed', $voucher->status);
        $this->assertDatabaseHas('warehouse_voucher_lines', [
            'warehouse_voucher_id' => $voucher->id,
            'product_id' => $product->id,
            'quantity' => 12,
            'unit_cost' => 81_000,
        ]);
        $this->assertSame(12, (int) WarehouseInventory::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock_quantity'));
        $this->assertDatabaseHas('warehouse_inventory_movements', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => WarehouseInventoryMovement::TYPE_INTAKE,
            'quantity' => 12,
            'reference_type' => 'warehouse_voucher',
            'reference_id' => $voucher->id,
            'unit_cost' => 81_000,
        ]);
    }

    public function test_outbound_voucher_is_transactional_when_stock_is_insufficient(): void
    {
        $actor = $this->adminUser();
        $warehouse = Warehouse::query()->create(['name' => 'Kho thiếu tồn', 'code' => 'LOW']);
        $product = Product::query()->create([
            'name' => 'Sản phẩm thiếu tồn',
            'sku' => 'LOW-001',
            'unit_price' => 250_000,
            'cost_price' => 90_000,
            'is_active' => true,
        ]);
        WarehouseInventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'stock_quantity' => 2,
            'pending_sales_quantity' => 0,
        ]);

        $response = $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PXK-LOW-001',
                'type' => 'outbound',
                'document_date' => '2026-07-23',
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_cost' => 90_000,
                'note' => 'Xuất kho thiếu tồn',
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('warehouse_vouchers', ['code' => 'PXK-LOW-001']);
        $this->assertSame(2, (int) WarehouseInventory::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock_quantity'));
    }

    public function test_warehouse_53_pages_use_same_linked_business_data(): void
    {
        $actor = $this->adminUser();
        $warehouse = Warehouse::query()->create(['name' => 'Kho báo cáo', 'code' => 'BC']);
        $product = Product::query()->create([
            'name' => 'Sản phẩm báo cáo',
            'sku' => 'BC-001',
            'unit_price' => 300_000,
            'cost_price' => 120_000,
            'is_active' => true,
        ]);

        $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PNK-BC-001',
                'type' => 'inbound',
                'document_date' => '2026-07-23',
                'product_id' => $product->id,
                'quantity' => 7,
                'unit_cost' => 120_000,
            ],
        ])->assertCreated();

        $service = app(PushsalePageService::class);
        $entryRows = $service->rows('5.3.1', request())['data'];
        $voucherRows = $service->rows('5.3.2', request())['data'];
        $movementRows = $service->rows('5.3.3', request())['data'];

        $this->assertTrue(collect($entryRows)->contains(fn (array $row): bool => $row['product'] === 'Sản phẩm báo cáo'));
        $this->assertTrue(collect($voucherRows)->contains(fn (array $row): bool => $row['voucher_code'] === 'PNK-BC-001' && $row['total_quantity'] === 7));
        $this->assertTrue(collect($movementRows)->contains(fn (array $row): bool => $row['reference'] === 'PNK-BC-001' && $row['quantity'] === 7));
    }

    private function adminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin kho',
            'email' => 'warehouse-admin-v98@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
