<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantEmail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Bootstrap tối thiểu cho môi trường production / fresh install:
 * đơn vị nội bộ + superadmin. Không seed dữ liệu demo nghiệp vụ.
 */
class PlatformAdminSeeder extends Seeder
{
    private const SUPERADMIN_EMAIL = 'superadmin@saleops.local';

    public function run(): void
    {
        $company = CompanyProvisioningService::internalCompany();
        $company->forceFill([
            'name' => TenantEmail::internalName(),
            'status' => 'active',
            'plan' => 'internal',
            'contact_email' => self::SUPERADMIN_EMAIL,
            'email_login_host' => TenantEmail::domain(),
        ])->save();

        $superadmin = User::query()->firstOrCreate(
            ['email' => self::SUPERADMIN_EMAIL],
            [
                'company_id' => $company->id,
                'name' => 'Super Admin',
                'password' => Hash::make(TenantEmail::defaultPassword()),
                'role' => UserRole::Admin,
                'is_owner' => true,
                'is_platform_admin' => true,
                'is_active' => true,
                'job_title' => 'Super Admin',
            ],
        );

        $superadmin->ensurePreferences();

        if (! $company->owner_user_id) {
            $company->update(['owner_user_id' => $superadmin->id]);
        }

        $this->command?->info('Platform bootstrap: superadmin + đơn vị nội bộ.');
    }
}
