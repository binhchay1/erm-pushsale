<?php

namespace Tests\Feature\Operations;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Operations\SaleOperationPolicy;
use App\Services\Operations\SaleOrderEditService;
use App\Services\Orders\OrderClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleFeedbackPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function openOrder(User $sale, array $extra = []): Order
    {
        return Order::query()->create(array_merge([
            'order_code' => null,
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách Test',
            'customer_phone' => '0905000222',
            'operation_stage' => OperationStage::NewCustomer->value,
            'delivery_status' => DeliveryStatus::DeliverNow->value,
            'closing_status' => ClosingStatus::Open->value,
            'discount' => 0,
        ], $extra));
    }

    public function test_sale_can_unclose_waiting_waybill_order(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale, [
            'order_code' => 'PS-UNCLOSE-1',
            'closed_at' => now(),
            'closing_status' => ClosingStatus::Closed->value,
            'delivery_status' => DeliveryStatus::WaitingWaybill->value,
            'operation_result' => 'closed_success',
        ]);

        $this->assertTrue(SaleOperationPolicy::canUnclose($order));

        $fresh = app(OrderClosingService::class)->unclose($order, $sale);

        $this->assertNull($fresh->closed_at);
        $this->assertSame(ClosingStatus::Open->value, $fresh->closing_status);
        $this->assertSame(DeliveryStatus::DeliverNow->value, $fresh->delivery_status);
        $this->assertSame('PS-UNCLOSE-1', $fresh->order_code);
    }

    public function test_sale_cannot_unclose_after_carrier_handoff(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale, [
            'order_code' => 'PS-UNCLOSE-2',
            'closed_at' => now(),
            'closing_status' => ClosingStatus::Closed->value,
            'delivery_status' => DeliveryStatus::WaitingWaybill->value,
            'tracking_number' => 'TRACK123',
        ]);

        $this->assertFalse(SaleOperationPolicy::canUnclose($order));

        $this->expectException(ValidationException::class);
        app(OrderClosingService::class)->unclose($order, $sale);
    }

    public function test_only_admin_can_delete_data_flag(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);

        $this->assertTrue(SaleOperationPolicy::canDeleteData($order, $admin));
        $this->assertFalse(SaleOperationPolicy::canDeleteData($order, $sale));
        $this->assertFalse(SaleOperationPolicy::canDeleteData($order, null));
    }

    public function test_sale_cannot_change_marketing_source_or_unit_price(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sourceA = MarketingSource::query()->create(['name' => 'Nguồn A', 'is_active' => true]);
        $sourceB = MarketingSource::query()->create(['name' => 'Nguồn B', 'is_active' => true]);
        $product = Product::query()->create([
            'name' => 'SP khóa giá',
            'type' => 'product',
            'sku' => 'SKU-LOCK',
            'unit_price' => 100_000,
            'is_active' => true,
        ]);

        $order = $this->openOrder($sale, ['marketing_source_id' => $sourceA->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'item_type' => 'product',
            'quantity' => 1,
            'unit_price' => 100_000,
        ]);

        $updatedBySale = app(SaleOrderEditService::class)->update($order->fresh('items'), $sale, [
            'marketing_source_id' => $sourceB->id,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'item_type' => 'product',
                'quantity' => 2,
                'unit_price' => 1,
            ]],
        ]);

        $this->assertSame($sourceA->id, (int) $updatedBySale->marketing_source_id);
        $this->assertSame(100_000, (int) $updatedBySale->items->first()->unit_price);
        $this->assertSame(2, (int) $updatedBySale->items->first()->quantity);

        $updatedByAdmin = app(SaleOrderEditService::class)->update($updatedBySale, $admin, [
            'marketing_source_id' => $sourceB->id,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'item_type' => 'product',
                'quantity' => 1,
                'unit_price' => 88_000,
            ]],
        ]);

        $this->assertSame($sourceB->id, (int) $updatedByAdmin->marketing_source_id);
        $this->assertSame(88_000, (int) $updatedByAdmin->items->first()->unit_price);
    }

    public function test_landing_order_without_catalog_items_uses_connection_products(): void
    {
        $parent = Product::query()->create([
            'name' => 'Set bông tai ngọc trai',
            'type' => 'product',
            'sku' => 'BT-PARENT',
            'unit_price' => 199_000,
            'is_active' => true,
        ]);
        $variantA = Product::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Set bông tai ngọc trai - Mẫu 1',
            'type' => 'product',
            'sku' => 'BT-M1',
            'unit_price' => 199_000,
            'is_active' => true,
        ]);
        $variantB = Product::query()->create([
            'parent_id' => $parent->id,
            'name' => 'Set bông tai ngọc trai - Mẫu 2',
            'type' => 'product',
            'sku' => 'BT-M2',
            'unit_price' => 219_000,
            'is_active' => true,
        ]);

        $source = MarketingSource::query()->create(['name' => 'Ladi test', 'is_active' => true]);
        $connection = \App\Models\LandingConnection::query()->create([
            'marketing_source_id' => $source->id,
            'name' => 'Ladi connection',
            'public_token' => 'tok-test',
            'is_active' => true,
            'is_approved' => true,
        ]);
        \App\Models\LandingConnectionProduct::query()->create([
            'landing_connection_id' => $connection->id,
            'product_id' => $parent->id,
            'item_type' => 'product',
            'quantity' => 1,
            'sort_order' => 1,
        ]);

        $lead = \App\Models\LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'lead-landing-products-1',
            'marketing_source_id' => $source->id,
            'landing_connection_id' => $connection->id,
            'customer_name' => 'Khách Ladi',
            'customer_phone' => '0905111222',
            'payload' => ['message' => 'Muốn mua'],
            'status' => 'processed',
        ]);

        $order = app(\App\Services\Leads\LeadOrderFactory::class)->createFromLead($lead, [
            'customer_name' => 'Khách Ladi',
            'customer_phone' => '0905111222',
            'items' => [['product_name' => 'Ghi chú text', 'quantity' => 1, 'unit_price' => 0]],
            'item_origin' => 'landing',
        ], User::factory()->create(['role' => UserRole::Sales]));

        $this->assertCount(2, $order->items);
        $productIds = $order->items->pluck('product_id')->sort()->values()->all();
        $this->assertSame([$variantA->id, $variantB->id], $productIds);
        $this->assertTrue($order->items->every(fn ($item) => (int) $item->quantity === 0));
        $this->assertTrue($order->items->every(fn ($item) => (int) $item->unit_price > 0));
    }

    public function test_sale_can_save_landing_line_without_catalog_product_and_zero_quantity(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);
        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Mua 3 TẶNG 1 Túi (Tổng 2kg) : 399.000đ + MIỄN SHIP',
            'item_type' => 'product',
            'quantity' => 0,
            'unit_price' => 399_000,
        ]);

        $this->actingAs($sale)
            ->post("/sales/orders/{$order->id}/details", [
                'items' => [[
                    'product_id' => null,
                    'product_name' => 'Mua 3 TẶNG 1 Túi (Tổng 2kg) : 399.000đ + MIỄN SHIP',
                    'item_type' => 'product',
                    'quantity' => 0,
                    'unit_price' => 1,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $item = $order->fresh('items')->items->first();

        $this->assertNull($item->product_id);
        $this->assertSame(0, (int) $item->quantity);
        // Sale không được hạ giá dòng Ladi chưa map catalog.
        $this->assertSame(399_000, (int) $item->unit_price);
    }

    public function test_order_cannot_be_closed_while_every_line_has_zero_quantity(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);
        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Sản phẩm Ladi',
            'item_type' => 'product',
            'quantity' => 0,
            'unit_price' => 399_000,
        ]);

        $this->expectException(ValidationException::class);
        app(OrderClosingService::class)->close($order->fresh('items'), $sale);
    }

    public function test_workspace_row_exposes_latest_internal_note(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale, ['sale_operation_note' => 'Ghi chú cũ']);
        $order->internalMessages()->create([
            'company_id' => $order->company_id ?? $sale->company_id,
            'author_user_id' => $sale->id,
            'author_name' => $sale->name,
            'author_role' => UserRole::Sales->value,
            'customer_phone' => $order->customer_phone,
            'message' => 'đơn đang giao hỏi khách time nhận hàng',
        ]);
        $order->load(['items', 'internalMessages']);

        $payload = \App\Services\Operations\OrderOperationPresenter::toArray($order, null, $sale);

        $this->assertSame('đơn đang giao hỏi khách time nhận hàng', $payload['latestInternalNote']);
        $this->assertTrue($payload['canUnclose'] === false);
    }
}
