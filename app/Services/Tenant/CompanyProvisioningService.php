<?php

namespace App\Services\Tenant;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Shops\ShopProvisioningService;
use App\Support\TenantEmail;
use App\Support\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyProvisioningService
{
    /**
     * Super admin tạo doanh nghiệp mới + admin tổng (chủ sở hữu).
     *
     * @return array{company: Company, owner: User, suggested_accounts: list<array{role: string, role_label: string, email: string}>, default_password: string}
     */
    public function createCommercialCompany(
        string $companyName,
        string $ownerName,
        ?string $slug = null,
        ?string $ownerEmail = null,
        ?string $password = null,
        ?string $contactEmail = null,
        ?string $contactPhone = null,
        ?string $plan = 'trial',
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $password = $password ?: TenantEmail::defaultPassword();
        $slug = $slug ?: Company::makeSlug($companyName);

        return DB::transaction(function () use (
            $companyName, $ownerName, $slug, $ownerEmail, $password,
            $contactEmail, $contactPhone, $plan, $expiresAt
        ) {
            $company = Company::query()->create([
                'name' => $companyName,
                'slug' => $slug,
                'status' => Company::STATUS_ACTIVE,
                'plan' => $plan ?? 'trial',
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'expires_at' => $expiresAt,
            ]);

            $ownerEmail = $ownerEmail ?: TenantEmail::forRole(UserRole::Admin, $company);

            $owner = User::query()->create([
                'company_id' => $company->id,
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'is_owner' => true,
                'org_level' => OrgLevel::Head,
                'is_team_leader' => true,
                'job_title' => __('messages.tenant.owner_title'),
            ]);

            $owner->ensurePreferences();
            $company->update(['owner_user_id' => $owner->id]);

            app(ShopProvisioningService::class)->ensureDefaultShop($company);

            return [
                'company' => $company,
                'owner' => $owner,
                'suggested_accounts' => TenantEmail::suggestedAccounts($company),
                'default_password' => $password,
            ];
        });
    }

    /**
     * Super admin thêm 1 admin (giám đốc) cho công ty đã có.
     *
     * @return array{admin: User, default_password: string}
     */
    public function createCompanyAdmin(
        Company $company,
        string $name,
        ?string $email = null,
        ?string $password = null,
    ): array {
        $password = $password ?: TenantEmail::defaultPassword();
        $email = $email ?: TenantEmail::forRole(UserRole::Admin, $company);

        $admin = User::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'is_owner' => false,
            'org_level' => OrgLevel::Head,
            'is_team_leader' => true,
            'job_title' => __('messages.tenant.owner_title'),
        ]);

        $admin->ensurePreferences();

        $defaultShop = app(ShopProvisioningService::class)->ensureDefaultShop($company);
        $defaultShop->users()->syncWithoutDetaching([$admin->id]);
        $admin->forceFill(['default_shop_id' => $defaultShop->id])->save();

        return ['admin' => $admin, 'default_password' => $password];
    }

    /** Công ty nội bộ (seed / vận hành ERM) — email dạng admin@saleops.local. */
    public static function internalCompany(): Company
    {
        return Company::query()->firstOrCreate(
            ['slug' => TenantEmail::internalSlug()],
            [
                'name' => (string) config('saleops.tenant.internal_name', 'ERM SaleOps (Nội bộ)'),
                'status' => Company::STATUS_ACTIVE,
                'plan' => 'internal',
            ],
        );
    }

    /** @param  callable(): void  $callback */
    public function runAsCompany(int $companyId, callable $callback): mixed
    {
        return app(TenantManager::class)->forCompany($companyId, $callback);
    }
}
