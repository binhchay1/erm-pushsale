<?php

namespace Tests\Feature\Warehouse;

use App\Enums\DeliveryStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shops\ShopProvisioningService;
use App\Services\Warehouse\BulkUpdateByCodeService;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUpdateByCodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0:User,1:Shop,2:Company}
     */
    private function actingShopUser(string $role = User::ROLE_ADMIN): array
    {
        $company = Company::query()->firstOrCreate(
            ['slug' => 'test-co'],
            ['name' => 'Test Co', 'status' => Company::STATUS_ACTIVE, 'plan' => 'pro'],
        );
        app(TenantManager::class)->set($company->id);

        $shop = app(ShopProvisioningService::class)->ensureDefaultShop($company);
        app(TenantManager::class)->setShop($shop->id);

        $user = User::factory()->create([
            'role' => $role,
            'company_id' => $company->id,
            'default_shop_id' => $shop->id,
        ]);
        $shop->users()->syncWithoutDetaching([$user->id]);

        return [$user, $shop, $company];
    }

    public function test_admin_can_open_bulk_update_by_code_page(): void
    {
        [$admin] = $this->actingShopUser();

        $this->actingAs($admin)
            ->get('/admin/warehouse/orders/update-by-code')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Warehouse/BulkUpdateByCode')
                ->has('actions')
                ->has('filterOptions.shippingServiceOptions')
                ->where('pageTitle', 'Cập nhật contact theo mã pushsale'));
    }

    public function test_accounting_can_open_bulk_update_by_code_page(): void
    {
        [$user] = $this->actingShopUser(User::ROLE_ACCOUNTING);

        $this->actingAs($user)
            ->get('/accounting/orders/update-by-code')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Warehouse/BulkUpdateByCode')
                ->where('activeMenuCode', '6.1')
                ->where('executeUrl', '/accounting/orders/update-by-code'));
    }

    public function test_bulk_update_order_fields_by_pushsale_code(): void
    {
        [$admin, $shop, $company] = $this->actingShopUser();
        $warehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'name' => 'Kho test',
        ]);
        $order = Order::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'order_code' => 'PS00184641173PS',
            'customer_name' => 'KH test',
            'customer_phone' => '0901234567',
            'delivery_status' => DeliveryStatus::DeliverNow->value,
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'warehouse_id' => null,
            'shipping_notes' => null,
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/update-by-code', [
                'code_type' => 'MHT',
                'codes' => $order->order_code,
                'action' => 'CAP_NHAT_DON',
                'warehouse_id' => $warehouse->id,
                'shipping_notes' => 'Gọi trước khi giao',
                'weight_grams' => 750,
            ])
            ->assertOk()
            ->assertJsonPath('success_count', 1);

        $order->refresh();
        $this->assertSame($warehouse->id, $order->warehouse_id);
        $this->assertSame('Gọi trước khi giao', $order->shipping_notes);
        $this->assertSame(750, data_get($order->shipping_geo, 'package_weight_grams'));
    }

    public function test_service_parses_codes_separated_by_semicolon_or_newline(): void
    {
        $service = app(BulkUpdateByCodeService::class);
        $this->assertSame(
            ['A', 'B', 'C'],
            $service->parseCodes("A;B\nC ; A"),
        );
    }
}
