<?php

namespace Database\Seeders;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Bộ dữ liệu demo đồng bộ toàn hệ thống (đa doanh nghiệp).
 *
 * Tài khoản chính (mật khẩu chung `password`):
 * - superadmin@saleops.local — super admin (chủ project): full dữ liệu nội bộ + quản trị nền tảng
 *   (tạo công ty + admin cho doanh nghiệp khác). KHÔNG thấy dữ liệu tenant khác.
 * - admin@saleops.local — quản trị công ty nội bộ.
 * - sales@saleops.local, marketing@saleops.local, … — trưởng các bộ phận nội bộ.
 *
 * Doanh nghiệp khách: tạo qua /platform/companies → email admin@{slug}.saleops.local
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoResetSeeder::class]);

        $company = CompanyProvisioningService::internalCompany();

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->call([
                AccountSeeder::class,
                CatalogSeeder::class,
                ProductTaxonomyDemoSeeder::class,
                InventorySeeder::class,
                MarketingCampaignSeeder::class,
                SalesPipelineSeeder::class,
                DataFilterHistorySeeder::class,
                LandingFlowSeeder::class,
                ShippingEventSeeder::class,
                DemoNotificationSeeder::class,
            ]);
        });

        $owner = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'superadmin@saleops.local')
            ->first();

        if ($owner) {
            // Super admin = chủ project: admin công ty nội bộ + quản trị nền tảng.
            $owner->update([
                'is_owner' => true,
                'is_platform_admin' => true,
                'company_id' => $company->id,
            ]);
            $company->update(['owner_user_id' => $owner->id, 'contact_email' => 'superadmin@saleops.local']);
        }
    }
}
