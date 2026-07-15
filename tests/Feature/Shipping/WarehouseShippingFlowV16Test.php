<?php

namespace Tests\Feature\Shipping;

use App\Events\OrderClosed;
use App\Jobs\CreateShipmentJob;
use App\Listeners\DispatchShipmentOnOrderClosed;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShippingPartnerConnection;
use App\Models\ShippingWebhookEvent;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseReturnReceipt;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WarehouseShippingFlowV16Test extends TestCase
{
    use RefreshDatabase;

    public function test_each_company_can_configure_the_same_shipping_provider(): void
    {
        $tenant = app(TenantManager::class);
        $firstCompanyId = $tenant->id();
        $second = Company::query()->create([
            'name' => 'Công ty thứ hai',
            'slug' => 'company-two',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'pro',
        ]);

        $first = ShippingPartnerConnection::forProvider('manual');
        $secondConnection = $tenant->forCompany(
            $second->id,
            fn () => ShippingPartnerConnection::forProvider('manual'),
        );

        $this->assertSame($firstCompanyId, $first->company_id);
        $this->assertSame($second->id, $secondConnection->company_id);
        $this->assertNotSame($first->id, $secondConnection->id);
    }

    public function test_closing_order_queues_default_carrier_only_when_auto_waybill_is_enabled(): void
    {
        Queue::fake();

        $company = Company::query()->findOrFail(app(TenantManager::class)->id());
        $company->update([
            'default_shipping_provider' => 'manual',
            'default_shipping_method' => 'manual',
        ]);
        ShippingPartnerConnection::forProvider('manual')->update([
            'is_enabled' => true,
            'integration_mode' => 'manual',
            'settings' => ['auto_create_waybill' => true],
        ]);

        $order = Order::query()->create([
            'order_code' => 'V16-AUTO-WAYBILL',
            'customer_name' => 'Khách tự động',
            'customer_phone' => '0900000001',
            'closed_at' => now(),
            'delivery_status' => 'waiting_waybill',
            'total' => 250_000,
            'amount_to_collect' => 250_000,
        ]);

        app(DispatchShipmentOnOrderClosed::class)->handle(new OrderClosed($order));

        Queue::assertPushed(CreateShipmentJob::class, static fn (CreateShipmentJob $job): bool =>
            $job->orderId === $order->id && $job->provider === 'manual'
        );
        $this->assertSame('manual', $order->fresh()->shipping_provider);
    }

    public function test_return_webhook_is_idempotent_and_updates_stock_and_carrier_costs_once(): void
    {
        ShippingPartnerConnection::forProvider('ghtk')->update([
            'is_enabled' => true,
            'settings' => ['auto_restock_return' => true],
        ]);

        $warehouse = Warehouse::query()->create(['name' => 'Kho V16']);
        $product = Product::query()->create([
            'name' => 'Sản phẩm V16',
            'sku' => 'V16-SKU',
            'unit_price' => 300_000,
            'cost_price' => 120_000,
            'is_active' => true,
        ]);
        $inventory = WarehouseInventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'stock_quantity' => 8,
        ]);
        $order = Order::query()->create([
            'order_code' => 'V16-RETURN-001',
            'warehouse_id' => $warehouse->id,
            'customer_name' => 'Khách hoàn',
            'customer_phone' => '0900000002',
            'closed_at' => now()->subDays(2),
            'inventory_deducted_at' => now()->subDays(2),
            'delivery_status' => 'delivering',
            'tracking_number' => 'GHTK-V16-001',
            'subtotal' => 600_000,
            'total' => 600_000,
            'amount_to_collect' => 600_000,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'item_type' => 'main',
            'quantity' => 2,
            'unit_price' => 300_000,
            'cost_price_snapshot' => 120_000,
        ]);
        Shipment::query()->create([
            'order_id' => $order->id,
            'provider' => 'ghtk',
            'partner_order_id' => $order->order_code,
            'tracking_number' => $order->tracking_number,
            'state' => Shipment::STATE_SUBMITTED,
            'cod_amount' => 600_000,
        ]);

        $payload = [
            'event_id' => 'ghtk-return-v16-001',
            'tracking_number' => $order->tracking_number,
            'order_code' => $order->order_code,
            'status' => 'returned',
            'event_time' => '2026-07-14T10:00:00+07:00',
            'shipping_fee' => 30_000,
            'return_fee' => 25_000,
            'cod_fee' => 5_000,
            'other_fee' => 2_000,
            'return_items' => [[
                'order_item_id' => $item->id,
                'product_id' => $product->id,
                'received_quantity' => 2,
                'restock_quantity' => 2,
                'condition' => 'sellable',
            ]],
        ];

        $service = app(ShippingWebhookService::class);
        $first = $service->process('ghtk', $payload);
        $second = $service->process('ghtk', $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ShippingWebhookEvent::query()->count());
        $this->assertSame(1, WarehouseReturnReceipt::query()->count());
        $this->assertSame(10, $inventory->fresh()->stock_quantity);

        $freshOrder = $order->fresh();
        $this->assertSame('returned', $freshOrder->delivery_status);
        $this->assertSame(30_000, (int) $freshOrder->carrier_service_fee);
        $this->assertSame(25_000, (int) $freshOrder->carrier_return_fee);
        $this->assertSame(5_000, (int) $freshOrder->cod_fee);
        $this->assertSame(2_000, (int) $freshOrder->carrier_other_fee);
        $this->assertNotNull($freshOrder->return_restocked_at);
    }
}
