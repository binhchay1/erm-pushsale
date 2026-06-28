<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Bộ dữ liệu demo đồng bộ toàn hệ thống (đa doanh nghiệp).
 *
 * Tài khoản (mật khẩu chung `password`):
 * - super@saleops.local        → super admin nền tảng (tạo doanh nghiệp khách).
 * - admin@saleops.local …      → công ty nội bộ ERM (email @saleops.local).
 *
 * Doanh nghiệp khách: super admin tạo qua /platform/companies
 * → email dạng admin@{slug}.saleops.local
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoResetSeeder::class]);

        $this->makePlatformAdmin();

        $company = CompanyProvisioningService::internalCompany();

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->call([
                AccountSeeder::class,
                CatalogSeeder::class,
                InventorySeeder::class,
                MarketingCampaignSeeder::class,
                SalesPipelineSeeder::class,
                ShippingEventSeeder::class,
                DemoNotificationSeeder::class,
            ]);
        });

        $owner = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'admin@saleops.local')
            ->first();

        if ($owner) {
            $owner->update(['is_owner' => true, 'company_id' => $company->id]);
            $company->update(['owner_user_id' => $owner->id, 'contact_email' => 'admin@saleops.local']);
        }
    }

    private function makePlatformAdmin(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super@saleops.local',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_platform_admin' => true,
        ]);

        $admin->ensurePreferences();
    }
}
