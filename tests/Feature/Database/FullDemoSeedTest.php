<?php

namespace Tests\Feature\Database;

use App\Models\ActivityLog;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Services\Pushsale\PushsalePageService;
use App\Models\Order;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\ActivityLogger;
use App\Support\TenantManager;
use Database\Seeders\AccountSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FullBusinessDemoSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\LandingFlowSeeder;
use Database\Seeders\MarketingCampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullDemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_database_seed_runs_without_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superadmin = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'superadmin@saleops.local')->first();
        $this->assertNotNull($superadmin);
        $this->assertTrue((bool) $superadmin->is_platform_admin);
        $this->assertSame(1, User::query()->withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_full_business_demo_seed_runs_without_error(): void
    {
        $this->seed(FullBusinessDemoSeeder::class);

        $superadmin = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'superadmin@saleops.local')->first();
        $this->assertNotNull($superadmin);
        $this->assertTrue((bool) $superadmin->is_platform_admin);

        $admin = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'admin@saleops.local')->first();
        $this->assertNotNull($admin);
        $this->assertFalse((bool) $admin->is_platform_admin);

        $this->assertGreaterThan(
            0,
            ActivityLog::query()->withoutTenant()->where('action', ActivityLogger::DATA_FILTER_SEARCHED)->count(),
            'Full seed phải có dữ liệu lịch sử lọc cho menu 1.7.3.'
        );

        $this->assertGreaterThan(
            0,
            ActivityLog::query()->withoutTenant()->where('action', ActivityLogger::AUTH_LOGIN_SUCCESS)->count(),
            'Full seed phải có dữ liệu lịch sử đăng nhập cho menu 1.7.1.'
        );

        $this->assertGreaterThan(
            0,
            User::query()->withoutGlobalScope(TenantScope::class)->whereNotNull('permissions')->count(),
            'Full seed phải có dữ liệu quyền đăng nhập cho menu 1.7.2.'
        );

        $this->assertGreaterThanOrEqual(60, ProductCategory::query()->count(), 'Full seed phải có dữ liệu phân loại sản phẩm cho popup.');
        $this->assertGreaterThanOrEqual(15, ProductAttribute::query()->count(), 'Full seed phải có dữ liệu thuộc tính sản phẩm cho popup.');
        $this->assertGreaterThanOrEqual(150, ProductAttributeValue::query()->count(), 'Full seed phải có dữ liệu giá trị thuộc tính cho popup.');

        $this->assertGreaterThanOrEqual(20, MarketingSource::query()->count(), 'Full seed phải có nhiều nguồn dữ liệu thật cho các dropdown marketing.');
        $this->assertGreaterThanOrEqual(100, LeadIngestion::query()->where('platform', 'manual')->count(), 'Full seed phải có nhiều contact nhập tay cho menu 2.6.2.');

        $manualRows = app(PushsalePageService::class)->rows('2.6.2', request())['data'];
        $this->assertNotEmpty($manualRows, 'Menu 2.6.2 phải đọc được contact nhập tay từ business thật.');
        $this->assertArrayHasKey('customer_phone', $manualRows[0]);
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
