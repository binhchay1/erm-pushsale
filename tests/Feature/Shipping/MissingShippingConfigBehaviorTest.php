<?php

namespace Tests\Feature\Shipping;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shipping\CreateShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Missing shipping config must NOT explode as HTTP 500 for create-shipment.
 * Close with empty provider must succeed (waybill auto-create soft-skips).
 */
class MissingShippingConfigBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_shipment_service_throws_friendly_runtime_when_no_carrier(): void
    {
        $order = $this->makeClosedOrder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/vận chuyển|carrier|cấu hình/i');

        app(CreateShipmentService::class)->createForOrder($order);
    }

    public function test_create_shipment_http_returns_422_json_not_500(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeClosedOrder($admin->company_id);

        $response = $this->actingAs($admin)
            ->postJson("/admin/shipping/orders/{$order->id}/create-shipment", []);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_close_order_with_empty_shipping_provider_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOpenOrder($admin);

        $response = $this->actingAs($admin)->post("/admin/sales/orders/{$order->id}/close", [
            'warehouse_id' => Warehouse::query()->value('id'),
            'shipping_provider' => null,
            'confirm_insufficient_stock' => true,
        ]);

        $response->assertRedirect();
        $this->assertNotNull($order->fresh()->closed_at);
    }

    private function makeAdmin(): User
    {
        $company = Company::query()->create([
            'name' => 'E2E Co',
            'code' => 'e2e-'.uniqid(),
            'is_active' => true,
        ]);

        return User::query()->create([
            'company_id' => $company->id,
            'name' => 'Admin E2E',
            'email' => 'admin-e2e-'.uniqid().'@saleops.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_platform_admin' => true,
        ]);
    }

    private function makeClosedOrder(?int $companyId = null): Order
    {
        $admin = $companyId
            ? User::query()->where('company_id', $companyId)->first() ?? $this->makeAdmin()
            : $this->makeAdmin();

        $order = $this->makeOpenOrder($admin);
        $order->update([
            'closed_at' => now(),
            'shipping_provider' => null,
        ]);

        return $order->fresh();
    }

    private function makeOpenOrder(User $admin): Order
    {
        $shop = Shop::query()->firstOrCreate(
            ['company_id' => $admin->company_id, 'code' => 'main'],
            ['name' => 'Main', 'is_active' => true],
        );

        $warehouse = Warehouse::query()->create([
            'company_id' => $admin->company_id,
            'shop_id' => $shop->id,
            'name' => 'WH E2E',
            'address' => 'HN',
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'company_id' => $admin->company_id,
            'shop_id' => $shop->id,
            'name' => 'SP E2E',
            'sku' => 'SKU-'.uniqid(),
            'price' => 50000,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'company_id' => $admin->company_id,
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'sale_user_id' => $admin->id,
            'customer_name' => 'KH E2E',
            'customer_phone' => '0912345678',
            'shipping_address' => 'HN',
            'shipping_provider' => null,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        return $order->fresh(['items']);
    }
}
