<?php

namespace Tests\Feature\Database;

use App\Models\Order;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantManager;
use Database\Seeders\AccountSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\LandingFlowSeeder;
use Database\Seeders\MarketingCampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullDemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_database_seed_runs_without_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superadmin = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'superadmin@saleops.local')->first();
        $this->assertNotNull($superadmin);
        $this->assertTrue((bool) $superadmin->is_platform_admin);

        $admin = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'admin@saleops.local')->first();
        $this->assertNotNull($admin);
        $this->assertFalse((bool) $admin->is_platform_admin);
    }

    public function test_landing_flow_seeder_produces_new_flow_data(): void
    {
        $company = CompanyProvisioningService::internalCompany();

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->seed(AccountSeeder::class);
            $this->seed(CatalogSeeder::class);
            $this->seed(InventorySeeder::class);
            $this->seed(MarketingCampaignSeeder::class);
            $this->seed(LandingFlowSeeder::class);

            // Đơn gộp (combo + upsale) có địa chỉ.
            $mergedOrder = Order::query()
                ->where('customer_phone', '0987000111')
                ->with('items')
                ->first();

            $this->assertNotNull($mergedOrder, 'Đơn gộp từ luồng Landing phải tồn tại.');
            $this->assertNotEmpty($mergedOrder->shipping_address);
            $this->assertGreaterThanOrEqual(2, $mergedOrder->items->count(), 'Đơn gộp phải có dòng combo + dòng upsell.');

            // Đơn đang chờ upsale (đã chia số, chưa upsale thêm).
            $awaitingOrder = Order::query()
                ->where('customer_phone', '0987000222')
                ->first();

            $this->assertNotNull($awaitingOrder, 'Đơn chờ upsale phải tồn tại ngay sau webhook.');
            $this->assertTrue($awaitingOrder->isAwaitingLandingUpsell());
        });
    }
}
