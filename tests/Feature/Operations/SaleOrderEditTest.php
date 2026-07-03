<?php

namespace Tests\Feature\Operations;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Operations\SaleOrderEditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private function openOrder(User $sale): Order
    {
        $order = Order::query()->create([
            'order_code' => 'PS-EDIT-1',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách Test',
            'customer_phone' => '0905000111',
            'operation_stage' => OperationStage::NewCustomer->value,
            'delivery_status' => DeliveryStatus::DeliverNow->value,
            'discount' => 0,
        ]);

        $order->items()->create([
            'product_name' => 'Sản phẩm gốc',
            'item_type' => 'product',
            'quantity' => 1,
            'unit_price' => 149_000,
        ]);

        return $order->fresh('items');
    }

    public function test_sale_can_edit_items_discount_and_carrier(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);

        $updated = app(SaleOrderEditService::class)->update($order, $sale, [
            'items' => [
                ['product_name' => 'Combo 2 Thỏi', 'item_type' => 'combo', 'quantity' => 1, 'unit_price' => 289_000, 'discount_amount' => 0],
                ['product_name' => 'Mua thêm Má Hồng', 'item_type' => 'upsell', 'quantity' => 1, 'unit_price' => 89_000, 'discount_amount' => 0],
            ],
            'discount' => 20_000,
            'shipping_provider' => 'ghtk',
        ]);

        // Giá trị cuối đơn = (289.000 + 89.000) − 20.000.
        $this->assertSame(378_000, (int) $updated->subtotal);
        $this->assertSame(358_000, (int) $updated->total);
        $this->assertSame(20_000, (int) $updated->discount);
        $this->assertSame('ghtk', $updated->shipping_provider);
        $this->assertNotNull($updated->carrier_name);
        $this->assertCount(2, $updated->items);
        $this->assertSame(358_000, $updated->effectiveRevenue());
    }

    public function test_sale_cannot_edit_order_of_another_sale(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Sales]);
        $intruder = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($owner);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(SaleOrderEditService::class)->update($order, $intruder, [
            'discount' => 50_000,
        ]);
    }
}
