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
 * Tài khoản chính (mật khẩu chung `password`):
 * - admin@saleops.local — admin công ty nội bộ + quản trị nền tảng (menu Doanh nghiệp, Cấu hình, Giám sát).
 * - sales@saleops.local, marketing@saleops.local, … — nhân sự nội bộ.
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
            $owner->update([
                'is_owner' => true,
                'is_platform_admin' => true,
                'company_id' => $company->id,
            ]);
            $company->update(['owner_user_id' => $owner->id, 'contact_email' => 'admin@saleops.local']);
        }
    }
}
