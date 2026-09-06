<?php

namespace Tests\Feature\Shops;

use App\Http\Middleware\SetCurrentShop;
use App\Models\Company;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_scope_hides_orders_from_other_shops(): void
    {
        $company = Company::query()->create([
            'name' => 'Demo Co',
            'slug' => 'demo-co-shop',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'trial',
        ]);

        $shopA = Shop::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop A',
            'code' => 'a',
            'is_default' => true,
            'is_active' => true,
        ]);
        $shopB = Shop::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop B',
            'code' => 'b',
            'is_default' => false,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'default_shop_id' => $shopA->id,
        ]);
        $shopA->users()->attach($admin->id);
        $shopB->users()->attach($admin->id);

        app(TenantManager::class)->set($company->id);
        app(TenantManager::class)->setShop($shopA->id);

        Order::query()->create([
            'shop_id' => $shopA->id,
            'company_id' => $company->id,
            'customer_name' => 'A',
            'customer_phone' => '0901000001',
            'closing_status' => 'open',
            'delivery_status' => 'deliver_now',
            'operation_stage' => 'new_customer',
        ]);
        Order::query()->withoutGlobalScopes()->create([
            'shop_id' => $shopB->id,
            'company_id' => $company->id,
            'customer_name' => 'B',
            'customer_phone' => '0901000002',
            'closing_status' => 'open',
            'delivery_status' => 'deliver_now',
            'operation_stage' => 'new_customer',
        ]);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, Order::query()->withoutShop()->count());
    }

    public function test_user_cannot_switch_to_inaccessible_shop(): void
    {
        $company = Company::query()->create([
            'name' => 'Demo Co 2',
            'slug' => 'demo-co-shop-2',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'trial',
        ]);

        $shopA = Shop::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop A',
            'code' => 'a',
            'is_default' => true,
            'is_active' => true,
        ]);
        $shopB = Shop::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop B',
            'code' => 'b',
            'is_default' => false,
            'is_active' => true,
        ]);

        $sales = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_SALES,
            'default_shop_id' => $shopA->id,
        ]);
        $shopA->users()->attach($sales->id);

        $this->actingAs($sales)
            ->post(route('shop.current'), ['shop_id' => $shopB->id])
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(route('shop.current'), ['shop_id' => $shopA->id])
            ->assertRedirect();

        $this->assertSame($shopA->id, session(SetCurrentShop::SESSION_KEY));
    }

    public function test_migration_backfill_creates_default_shop_for_company(): void
    {
        $company = Company::query()->create([
            'name' => 'Backfill Co',
            'slug' => 'backfill-co',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'trial',
        ]);

        // RefreshDatabase đã chạy migration; ensureDefaultShop vẫn idempotent.
        $shop = app(\App\Services\Shops\ShopProvisioningService::class)->ensureDefaultShop($company);

        $this->assertTrue($shop->is_default);
        $this->assertSame('main', $shop->code);
        $this->assertSame(1, Shop::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('is_default', true)->count());
    }
}
