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

    public function test_sale_can_set_confirmed_delivery_address_and_service(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);
        $order->update(['shipping_address' => 'Địa chỉ gốc từ landing']);

        $updated = app(SaleOrderEditService::class)->update($order, $sale, [
            'shipping_provider' => 'ghtk',
            'shipping_service' => 'road',
            'address_detail' => '123 Đội Cấn',
            'province_code' => 1,   // Thành phố Hà Nội
            'district_code' => 1,   // Quận Ba Đình
            'ward_code' => 1,       // Phường Phúc Xá
        ]);

        $this->assertSame('123 Đội Cấn, Phường Phúc Xá, Quận Ba Đình, Thành phố Hà Nội', $updated->shipping_address_2);
        $this->assertSame($updated->shipping_address_2, $updated->effectiveShippingAddress());
        $this->assertSame('Thành phố Hà Nội', $updated->shipping_geo['province']);
        $this->assertSame('Quận Ba Đình', $updated->shipping_geo['district']);
        $this->assertSame('Phường Phúc Xá', $updated->shipping_geo['ward']);
        $this->assertSame('road', $updated->shipping_geo['service_code']);
        $this->assertSame('Đường bộ', $updated->shipping_geo['service']);
    }

    public function test_new_2025_address_mode_uses_two_levels_and_receiver(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);

        $updated = app(SaleOrderEditService::class)->update($order, $sale, [
            'address_mode' => 'new',
            'address_detail' => '12 Ngõ Nhỏ',
            'province_code' => '01',   // Hà Nội (2 cấp)
            'ward_code' => '00004',    // Ba Đình
            'receiver_is_customer' => false,
            'receiver_name' => 'Người Nhận Khác',
            'receiver_phone' => '0912345678',
        ]);

        $this->assertSame('new', $updated->shipping_geo['mode']);
        $this->assertSame('Hà Nội', $updated->shipping_geo['province']);
        $this->assertSame('Ba Đình', $updated->shipping_geo['ward']);
        $this->assertNull($updated->shipping_geo['district']);
        $this->assertSame('12 Ngõ Nhỏ, Ba Đình, Hà Nội', $updated->shipping_address_2);

        $this->assertSame('Người Nhận Khác', $updated->receiver_name);
        $this->assertSame('0912345678', $updated->receiver_phone);
        $this->assertSame('Người Nhận Khác', $updated->effectiveReceiverName());
        $this->assertSame('0912345678', $updated->effectiveReceiverPhone());
    }

    public function test_receiver_defaults_to_customer_when_flag_true(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);
        $order->update(['receiver_name' => 'Cũ', 'receiver_phone' => '0900000000']);

        $updated = app(SaleOrderEditService::class)->update($order, $sale, [
            'receiver_is_customer' => true,
        ]);

        $this->assertNull($updated->receiver_name);
        $this->assertNull($updated->receiver_phone);
        $this->assertSame($updated->customer_name, $updated->effectiveReceiverName());
        $this->assertSame($updated->customer_phone, $updated->effectiveReceiverPhone());
    }

    public function test_effective_address_falls_back_to_landing_when_no_confirmed(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->openOrder($sale);
        $order->update(['shipping_address' => 'Địa chỉ gốc từ landing']);

        $this->assertSame('Địa chỉ gốc từ landing', $order->fresh()->effectiveShippingAddress());
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
