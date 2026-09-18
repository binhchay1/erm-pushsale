<?php

namespace Tests\Feature\Orders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Inventory\InventoryIntakeService;
use App\Services\Orders\OrderClosingService;
use App\Services\Operations\SaleOperationStatusService;
use App\Services\Shipping\CreateShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * End-to-end coverage for chốt đơn after allowing negative stock (xuất âm).
 * Uses sqlite :memory: — no leftover data on live DB.
 */
class OrderCloseNegativeStockFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_succeeds_when_stock_is_zero(): void
    {
        [$admin, $order, $warehouse, $product] = $this->seedOpenOrder(stock: 0, qty: 3);

        $closed = app(OrderClosingService::class)->close($order, $admin, [
            'warehouse_id' => $warehouse->id,
            'confirm_insufficient_stock' => false,
        ]);

        $this->assertNotNull($closed->closed_at);
        $this->assertSame(ClosingStatus::Closed->value, $closed->closing_status);
        $this->assertSame(DeliveryStatus::WaitingWaybill->value, $closed->delivery_status);
        $this->assertNotEmpty($closed->order_code);
        $this->assertSame(0, $this->stockOf($warehouse->id, $product->id), 'Chốt đơn chưa trừ kho — trừ khi tạo vận đơn');
    }

    public function test_close_succeeds_when_stock_is_insufficient_without_confirm_flag(): void
    {
        [$admin, $order, $warehouse, $product] = $this->seedOpenOrder(stock: 1, qty: 5);

        $closed = app(OrderClosingService::class)->close($order, $admin, [
            'warehouse_id' => $warehouse->id,
            // Không gửi confirm — vẫn phải chốt được (xuất âm).
        ]);

        $this->assertNotNull($closed->closed_at);
        $this->assertTrue(
            ! app(InventoryDeductionService::class)->hasSufficientStock($order->fresh('items')),
            'Tồn vẫn thiếu trước khi trừ kho',
        );
        $this->assertSame(1, $this->stockOf($warehouse->id, $product->id));
    }

    public function test_http_close_endpoint_allows_insufficient_stock(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 0, qty: 2);

        $this->actingAs($admin)
            ->from('/admin/dashboard')
            ->post("/admin/sales/orders/{$order->id}/close", [
                'warehouse_id' => $warehouse->id,
                'confirm_insufficient_stock' => false,
            ])
            ->assertRedirect('/admin/dashboard')
            ->assertSessionHasNoErrors();

        $this->assertNotNull($order->fresh()->closed_at);
    }

    public function test_operation_status_closed_success_closes_with_zero_stock(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 0, qty: 1);

        $fresh = app(SaleOperationStatusService::class)->applyStatus($order, $admin, [
            'operation_result' => OperationResult::ClosedSuccess->value,
            'confirm_insufficient_stock' => false,
        ]);

        $this->assertNotNull($fresh->closed_at);
        $this->assertSame(OperationResult::ClosedSuccess->value, $fresh->operation_result);
        $this->assertSame($warehouse->id, (int) $fresh->warehouse_id);
    }

    public function test_bulk_close_allows_insufficient_stock(): void
    {
        [$admin, $orderA, $warehouse] = $this->seedOpenOrder(stock: 0, qty: 2);
        [, $orderB] = $this->seedOpenOrder(stock: 0, qty: 4, admin: $admin, warehouse: $warehouse);

        $this->actingAs($admin)
            ->from('/admin/dashboard')
            ->post('/admin/sales/orders/bulk-close', [
                'order_ids' => [$orderA->id, $orderB->id],
                'confirm_insufficient_stock' => false,
            ])
            ->assertRedirect('/admin/dashboard');

        $this->assertNotNull($orderA->fresh()->closed_at);
        $this->assertNotNull($orderB->fresh()->closed_at);
    }

    public function test_deduct_for_order_drives_stock_negative(): void
    {
        [$admin, $order, $warehouse, $product] = $this->seedOpenOrder(stock: 2, qty: 5);
        app(OrderClosingService::class)->close($order, $admin, ['warehouse_id' => $warehouse->id]);

        app(InventoryDeductionService::class)->deductForOrder($order->fresh('items'), $admin);

        $this->assertSame(-3, $this->stockOf($warehouse->id, $product->id));
        $this->assertNotNull($order->fresh()->inventory_deducted_at);
        $this->assertDatabaseHas('warehouse_inventory_movements', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => WarehouseInventoryMovement::TYPE_DEDUCTION,
            'quantity' => -5,
            'stock_after' => -3,
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_deduct_for_order_is_idempotent_when_already_deducted(): void
    {
        [$admin, $order, $warehouse, $product] = $this->seedOpenOrder(stock: 0, qty: 2);
        app(OrderClosingService::class)->close($order, $admin, ['warehouse_id' => $warehouse->id]);
        $svc = app(InventoryDeductionService::class);

        $svc->deductForOrder($order->fresh('items'), $admin);
        $svc->deductForOrder($order->fresh('items'), $admin);

        $this->assertSame(-2, $this->stockOf($warehouse->id, $product->id));
        $this->assertSame(
            1,
            WarehouseInventoryMovement::query()
                ->where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->where('type', WarehouseInventoryMovement::TYPE_DEDUCTION)
                ->count(),
        );
    }

    public function test_manual_export_allows_negative_stock(): void
    {
        [$admin, , $warehouse, $product] = $this->seedOpenOrder(stock: 1, qty: 1);

        app(InventoryIntakeService::class)->export(
            $warehouse->id,
            $product->id,
            4,
            $admin,
            'Xuất âm kiểm thử',
        );

        $this->assertSame(-3, $this->stockOf($warehouse->id, $product->id));
    }

    public function test_create_shipment_no_longer_blocks_on_insufficient_stock(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 0, qty: 3);
        app(OrderClosingService::class)->close($order, $admin, ['warehouse_id' => $warehouse->id]);

        try {
            app(CreateShipmentService::class)->createForOrder($order->fresh(['items', 'warehouse', 'company']));
            $this->fail('Expected carrier/config RuntimeException, not stock block');
        } catch (RuntimeException $e) {
            $this->assertDoesNotMatchRegularExpression('/hết hàng|out of stock|tồn kho|không đủ/i', $e->getMessage());
        }
    }

    public function test_http_create_shipment_does_not_return_out_of_stock_when_stock_missing(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 0, qty: 2);
        app(OrderClosingService::class)->close($order, $admin, ['warehouse_id' => $warehouse->id]);

        $response = $this->actingAs($admin)
            ->postJson("/admin/shipping/orders/{$order->id}/create-shipment", []);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $message = (string) $response->json('message');
        $this->assertDoesNotMatchRegularExpression('/hết hàng|out of stock|tồn kho|không đủ/i', $message);
    }

    public function test_close_without_warehouse_still_rejected(): void
    {
        [$admin, $order] = $this->seedOpenOrder(stock: 10, qty: 1, withWarehouse: false);

        $this->expectException(ValidationException::class);
        app(OrderClosingService::class)->close($order, $admin, [
            'confirm_insufficient_stock' => true,
        ]);
    }

    public function test_close_without_sellable_qty_still_rejected(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 10, qty: 1, withItem: false);

        $this->expectException(ValidationException::class);
        app(OrderClosingService::class)->close($order, $admin, [
            'warehouse_id' => $warehouse->id,
        ]);
    }

    public function test_close_already_closed_still_rejected(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 0, qty: 1);
        app(OrderClosingService::class)->close($order, $admin, ['warehouse_id' => $warehouse->id]);

        $this->expectException(ValidationException::class);
        app(OrderClosingService::class)->close($order->fresh(), $admin, ['warehouse_id' => $warehouse->id]);
    }

    public function test_close_cancelled_order_still_rejected(): void
    {
        [$admin, $order, $warehouse] = $this->seedOpenOrder(stock: 5, qty: 1);
        $order->update([
            'closing_status' => ClosingStatus::Cancelled->value,
            'delivery_status' => DeliveryStatus::CancelClosing->value,
        ]);

        $this->expectException(ValidationException::class);
        app(OrderClosingService::class)->close($order->fresh(), $admin, ['warehouse_id' => $warehouse->id]);
    }

    public function test_assert_can_close_is_noop_even_when_stock_missing(): void
    {
        [, $order] = $this->seedOpenOrder(stock: 0, qty: 9);

        app(InventoryDeductionService::class)->assertCanClose($order, false);
        app(InventoryDeductionService::class)->assertCanClose($order, true);

        $this->assertTrue(true);
    }

    /**
     * @return array{0: User, 1: Order, 2: Warehouse|null, 3: Product|null}
     */
    private function seedOpenOrder(
        int $stock,
        int $qty,
        bool $withWarehouse = true,
        bool $withItem = true,
        ?User $admin = null,
        ?Warehouse $warehouse = null,
    ): array {
        if (! $admin) {
            $company = Company::query()->create([
                'name' => 'Close Co',
                'slug' => 'close-'.uniqid(),
                'status' => Company::STATUS_ACTIVE,
            ]);
            app(\App\Support\TenantManager::class)->set($company->id);

            $admin = User::query()->create([
                'company_id' => $company->id,
                'name' => 'Admin Close',
                'email' => 'close-'.uniqid().'@saleops.local',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_platform_admin' => true,
            ]);
        }

        $shop = Shop::query()->firstOrCreate(
            ['company_id' => $admin->company_id, 'code' => 'main-close'],
            ['name' => 'Main Close', 'is_active' => true],
        );

        if ($withWarehouse && ! $warehouse) {
            $warehouse = Warehouse::query()->create([
                'company_id' => $admin->company_id,
                'shop_id' => $shop->id,
                'name' => 'WH Close',
                'address' => 'HN',
                'sort_order' => 0,
            ]);
        }

        $product = null;
        if ($withWarehouse && $warehouse && $withItem) {
            $product = Product::query()->create([
                'company_id' => $admin->company_id,
                'shop_id' => $shop->id,
                'name' => 'SP Close',
                'sku' => 'SKU-CLOSE-'.uniqid(),
                'unit_price' => 100_000,
                'cost_price' => 40_000,
                'is_active' => true,
            ]);

            WarehouseInventory::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'stock_quantity' => $stock,
                'pending_sales_quantity' => 0,
            ]);
        }

        $order = Order::query()->create([
            'company_id' => $admin->company_id,
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse?->id,
            'sale_user_id' => $admin->id,
            'customer_name' => 'KH Close Test',
            'customer_phone' => '090'.random_int(1000000, 9999999),
            'shipping_address' => 'HN',
            'closing_status' => ClosingStatus::Open->value,
            'total' => $withItem ? 100_000 * max(1, $qty) : 0,
            'deposit' => 0,
        ]);

        if ($withItem && $product) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'unit_price' => 100_000,
                'cost_price' => 40_000,
            ]);
        }

        return [$admin, $order->fresh('items'), $warehouse, $product];
    }

    private function stockOf(int $warehouseId, int $productId): int
    {
        return (int) WarehouseInventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('stock_quantity');
    }
}
