<?php

namespace Tests\Feature\Pancake;

use App\Enums\DeliveryStatus;
use App\Enums\IntegrationPlatform;
use App\Enums\UserRole;
use App\Jobs\Pancake\SyncInventoryToPancakeJob;
use App\Jobs\Pancake\SyncOrderToPancakeJob;
use App\Models\Company;
use App\Models\IntegrationConnection;
use App\Models\Order;
use App\Models\PancakeSyncRecord;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventoryMovement;
use App\Services\Pancake\PancakeApiClient;
use App\Services\Pancake\PancakeConnectionResolver;
use App\Services\Pancake\PancakeOutboundSyncService;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PancakeOutboundSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_order_shipping_calls_pancake_api_and_updates_sync_record(): void
    {
        $company = Company::query()->first();
        $shop = Shop::query()->create([
            'company_id' => $company->id,
            'name' => 'Shop Test',
            'code' => 'ST',
            'is_default' => true,
            'is_active' => true,
        ]);
        $sale = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Sales,
        ]);

        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);
        $connection->update([
            'is_enabled' => true,
            'credentials' => [
                'shop_id' => 'shop_123',
                'api_key' => 'secret_api_key',
            ],
        ]);

        $order = Order::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'order_code' => 'ORD-SYNC-01',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách Test',
            'customer_phone' => '0912345678',
            'closing_status' => 'closed',
            'delivery_status' => DeliveryStatus::Delivering->value,
            'shipping_provider' => 'viettel_post',
            'tracking_number' => 'VT123456789VN',
            'carrier_service_fee' => 30000,
        ]);

        PancakeSyncRecord::query()->create([
            'company_id' => $company->id,
            'integration_connection_id' => $connection->id,
            'shop_id' => 'shop_123',
            'external_type' => PancakeSyncRecord::TYPE_ORDER,
            'external_id' => 'PK-ORDER-999',
            'order_id' => $order->id,
            'status' => 'synced',
        ]);

        Http::fake([
            'https://pos.pages.fm/api/v1/shops/shop_123/orders/PK-ORDER-999*' => Http::response([
                'success' => true,
                'data' => ['id' => 'PK-ORDER-999', 'status' => 2],
            ], 200),
        ]);

        $service = app(PancakeOutboundSyncService::class);
        $result = $service->syncOrderShipping($order);

        $this->assertNotNull($result);
        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/shops/shop_123/orders/PK-ORDER-999')
                && $request->method() === 'PUT'
                && $request['tracking_number'] === 'VT123456789VN'
                && $request['status'] === 2;
        });

        $record = PancakeSyncRecord::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($record->last_synced_at);
        $this->assertSame('VT123456789VN', $record->metadata['last_outbound_sync']['tracking_number']);
    }

    public function test_sync_order_shipping_job_processes_order(): void
    {
        $company = Company::query()->first();
        $shop = Shop::query()->create([
            'company_id' => $company->id,
            'name' => 'Shop Test',
            'code' => 'ST2',
            'is_default' => true,
            'is_active' => true,
        ]);
        $sale = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Sales,
        ]);

        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);
        $connection->update([
            'is_enabled' => true,
            'credentials' => [
                'shop_id' => 'shop_123',
                'api_key' => 'secret_api_key',
            ],
        ]);

        $order = Order::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'order_code' => 'ORD-SYNC-02',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách Job',
            'customer_phone' => '0912345679',
            'tracking_number' => 'GHTK998877',
            'delivery_status' => DeliveryStatus::Delivered->value,
        ]);

        PancakeSyncRecord::query()->create([
            'company_id' => $company->id,
            'integration_connection_id' => $connection->id,
            'shop_id' => 'shop_123',
            'external_type' => PancakeSyncRecord::TYPE_ORDER,
            'external_id' => 'PK-ORDER-888',
            'order_id' => $order->id,
            'status' => 'synced',
        ]);

        Http::fake([
            'https://pos.pages.fm/api/v1/shops/shop_123/orders/PK-ORDER-888*' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $job = new SyncOrderToPancakeJob($order->id);
        app()->call([$job, 'handle']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/shops/shop_123/orders/PK-ORDER-888')
                && $request['tracking_number'] === 'GHTK998877'
                && $request['status'] === 3;
        });
    }

    public function test_sync_now_endpoint_fetches_orders_from_pancake(): void
    {
        $company = Company::query()->first();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);
        $connection->update([
            'is_enabled' => true,
            'credentials' => [
                'shop_id' => 'shop_123',
                'api_key' => 'secret_api_key',
            ],
        ]);

        Http::fake([
            'https://pos.pages.fm/api/v1/shops/shop_123/orders*' => Http::response([
                'data' => [
                    [
                        'id' => 'PK-FETCH-101',
                        'customer_name' => 'Khách Sync Now',
                        'customer_phone' => '0988776655',
                        'items' => [
                            ['product_name' => 'Serum Trắng Da', 'quantity' => 1, 'unit_price' => 250000],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/integrations/pancake/sync-now')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('pancake_sync_records', [
            'external_id' => 'PK-FETCH-101',
        ]);
    }
}
