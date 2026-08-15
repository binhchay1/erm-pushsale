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
}
