<?php

namespace Tests\Feature\Operations;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Customers\CustomerProfileService;
use App\Services\Operations\OrderOperationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderOperationPresenterProductBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_presenter_keeps_same_main_and_upsell_breakdown_for_profile_sale_warehouse_accounting(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);

        $order = Order::query()->create([
            'order_code' => 'PS-OPS-91',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách đồng bộ upsale',
            'customer_phone' => '0912345678',
            'operation_stage' => OperationStage::Care1->value,
            'delivery_status' => DeliveryStatus::Delivered->value,
            'data_arrived_at' => now()->subMinutes(10),
            'assigned_at' => now()->subMinutes(9),
            'closed_at' => now()->subMinutes(5),
            'subtotal' => 458_000,
            'discount' => 0,
            'vat' => 0,
            'shipping_fee_collected' => 0,
            'total' => 458_000,
        ]);

        $order->items()->create([
            'product_name' => 'Gói máy dán cao cấp',
            'item_type' => 'product',
            'origin' => 'landing_main',
            'quantity' => 1,
            'unit_price' => 299_000,
        ]);

        $order->items()->create([
            'product_name' => 'Gói máy dán — size nhỏ',
            'item_type' => 'upsell',
            'origin' => 'landing_upsell',
            'quantity' => 1,
            'unit_price' => 159_000,
        ]);

        $payload = OrderOperationPresenter::toArray($order->fresh(['items', 'saleUser']));

        $this->assertSame(458_000, (int) $payload['subtotal']);
        $this->assertSame(0, (int) $payload['discount']);
        $this->assertSame(0, (int) $payload['vat']);
        $this->assertSame(0, (int) $payload['shippingFeeCollected']);
        $this->assertSame(458_000, (int) $payload['total']);

        $this->assertCount(2, $payload['products']);
        $this->assertSame('Gói máy dán cao cấp', $payload['products'][0]['productName']);
        $this->assertFalse($payload['products'][0]['isUpsell']);
        $this->assertSame('product', $payload['products'][0]['itemType']);
        $this->assertSame(1, (int) $payload['products'][0]['quantity']);
        $this->assertSame(299_000, (int) $payload['products'][0]['unitPrice']);

        $this->assertSame('Gói máy dán — size nhỏ', $payload['products'][1]['productName']);
        $this->assertTrue($payload['products'][1]['isUpsell']);
        $this->assertSame('upsell', $payload['products'][1]['itemType']);
        $this->assertSame('landing_upsell', $payload['products'][1]['origin']);
        $this->assertSame(1, (int) $payload['products'][1]['quantity']);
        $this->assertSame(159_000, (int) $payload['products'][1]['unitPrice']);
    }

    public function test_customer_profile_service_uses_the_same_operation_presenter_product_contract(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);

        $order = Order::query()->create([
            'order_code' => 'PS-PROFILE-91',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách hồ sơ upsale',
            'customer_phone' => '0987654321',
            'operation_stage' => OperationStage::Care1->value,
            'delivery_status' => DeliveryStatus::Delivered->value,
            'data_arrived_at' => now(),
            'assigned_at' => now(),
            'closed_at' => now(),
            'subtotal' => 458_000,
            'total' => 458_000,
        ]);

        $order->items()->createMany([
            [
                'product_name' => 'Gói máy dán cao cấp',
                'item_type' => 'product',
                'origin' => 'landing_main',
                'quantity' => 1,
                'unit_price' => 299_000,
            ],
            [
                'product_name' => 'Gói máy dán — size nhỏ',
                'item_type' => 'upsell',
                'origin' => 'landing_upsell',
                'quantity' => 1,
                'unit_price' => 159_000,
            ],
        ]);

        $filter = \App\Data\Customers\CustomerProfileFilterData::fromRequest(Request::create('/customers', 'GET', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'date_type' => 'data_arrival',
            'per_page' => 20,
            'page' => 1,
        ]));

        $rows = app(CustomerProfileService::class)->paginate($filter)['rows']['data'];
        $row = collect($rows)->firstWhere('id', (string) $order->id);

        $this->assertNotNull($row);
        $this->assertSame(458_000, (int) $row['total']);
        $this->assertCount(2, $row['products']);
        $this->assertFalse($row['products'][0]['isUpsell']);
        $this->assertTrue($row['products'][1]['isUpsell']);
    }
}
