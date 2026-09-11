<?php

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\Pushsale\PushsalePageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WarehouseVoucherBusinessLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_inbound_draft_save_does_not_touch_inventory(): void
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
                'lines' => [[
                    'product_id' => $product->id,
                    'document_quantity' => 12,
                    'quantity' => 12,
                    'unit_cost' => 81_000,
                    'batch_code' => 'LO-001',
                    'location_code' => 'A-01',
                    'note' => 'Nhập kho kiểm thử',
                ]],
                'note' => 'Nhập kho kiểm thử',
            ],
        ]);

        $response->assertCreated()->assertJsonPath('ok', true)->assertJsonPath('voucher.status', 'draft');

        $voucher = WarehouseVoucher::query()->where('code', 'PNK-TEST-001')->firstOrFail();
        $this->assertSame('draft', $voucher->status);
        $this->assertDatabaseHas('warehouse_voucher_lines', [
            'warehouse_voucher_id' => $voucher->id,
            'product_id' => $product->id,
            'quantity' => 12,
            'unit_cost' => 81_000,
        ]);
        $this->assertSame(0, (int) WarehouseInventory::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock_quantity'));
        $this->assertDatabaseMissing('warehouse_inventory_movements', [
            'reference_type' => 'warehouse_voucher',
            'reference_id' => $voucher->id,
        ]);
    }

    public function test_complete_inbound_voucher_applies_inventory_and_linked_movement(): void
    {
        $actor = $this->adminUser();
        $warehouse = Warehouse::query()->create(['name' => 'Kho hoàn thành', 'code' => 'DONE']);
        $product = Product::query()->create([
            'name' => 'Sản phẩm hoàn thành',
            'sku' => 'DONE-001',
            'unit_price' => 199_000,
            'cost_price' => 80_000,
            'is_active' => true,
        ]);

        $create = $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PNK-DONE-001',
                'type' => 1,
                'document_date' => '2026-07-23',
                'product_id' => $product->id,
                'document_quantity' => 12,
                'quantity' => 12,
                'unit_cost' => 81_000,
            ],
        ])->assertCreated();

        $voucherId = (int) $create->json('voucher.id');

        $this->actingAs($actor)
            ->postJson("/admin/warehouse/vouchers/entry/records/{$voucherId}/complete")
            ->assertOk()
            ->assertJsonPath('voucher.status', 'confirmed');

        $this->assertSame(12, (int) WarehouseInventory::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock_quantity'));
        $this->assertDatabaseHas('warehouse_inventory_movements', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => WarehouseInventoryMovement::TYPE_INTAKE,
            'quantity' => 12,
            'reference_type' => 'warehouse_voucher',
            'reference_id' => $voucherId,
            'unit_cost' => 81_000,
        ]);
    }

    public function test_outbound_complete_is_transactional_when_stock_is_insufficient(): void
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

        $create = $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PXK-LOW-001',
                'type' => 'outbound',
                'document_date' => '2026-07-23',
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_cost' => 90_000,
                    'note' => 'Xuất kho thiếu tồn',
                ]],
            ],
        ])->assertCreated();

        $voucherId = (int) $create->json('voucher.id');

        $this->actingAs($actor)
            ->postJson("/admin/warehouse/vouchers/entry/records/{$voucherId}/complete")
            ->assertUnprocessable();

        $this->assertDatabaseHas('warehouse_vouchers', ['code' => 'PXK-LOW-001', 'status' => 'draft']);
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

        $create = $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PNK-BC-001',
                'type' => 'inbound',
                'document_date' => '2026-07-23',
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity' => 7,
                    'unit_cost' => 120_000,
                ]],
            ],
        ])->assertCreated();

        $voucherId = (int) $create->json('voucher.id');
        $this->actingAs($actor)->postJson("/admin/warehouse/vouchers/entry/records/{$voucherId}/complete")->assertOk();

        $this->assertDatabaseHas('warehouse_voucher_lines', [
            'warehouse_voucher_id' => $voucherId,
            'product_id' => $product->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('warehouse_inventory_movements', [
            'reference_type' => 'warehouse_voucher',
            'reference_id' => $voucherId,
            'product_id' => $product->id,
            'quantity' => 7,
        ]);

        $service = app(PushsalePageService::class);
        $voucherRows = $service->rows('5.3.2', request())['data'];
        $movementRows = $service->rows('5.3.3', request())['data'];

        $this->assertTrue(
            collect($voucherRows)->contains(fn (array $row): bool => ($row['voucher_code'] ?? null) === 'PNK-BC-001' && (int) ($row['total_quantity'] ?? 0) === 7),
            '5.3.2 missing voucher PNK-BC-001: '.json_encode($voucherRows),
        );
        $this->assertTrue(
            collect($movementRows)->contains(fn (array $row): bool => ($row['reference'] ?? null) === 'PNK-BC-001' && (int) ($row['quantity'] ?? 0) === 7),
            '5.3.3 missing linked movement: '.json_encode($movementRows),
        );
    }

    public function test_import_csv_parses_catalog_products_only(): void
    {
        $actor = $this->adminUser();
        $product = Product::query()->create([
            'name' => 'Sản phẩm import',
            'sku' => 'IMP-001',
            'unit_price' => 100_000,
            'cost_price' => 40_000,
            'is_active' => true,
        ]);

        $csv = "sku,quantity,document_quantity,unit_cost\nIMP-001,3,3,40000\n";
        $file = UploadedFile::fake()->createWithContent('voucher.csv', $csv);

        $this->actingAs($actor)
            ->postJson('/admin/warehouse/vouchers/entry/import', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('lines.0.product_id', $product->id)
            ->assertJsonPath('lines.0.quantity', 3);
    }

    private function adminUser(): User
    {
        $companyId = app(\App\Support\TenantManager::class)->id();

        return User::query()->create([
            'name' => 'Admin kho',
            'email' => 'warehouse-admin-v98@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'company_id' => $companyId,
        ]);
    }
}
