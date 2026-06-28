<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Company;

/**
 * Quy ước email đa doanh nghiệp (giống pushsale.vn):
 *
 * - Nội bộ:  {role}@saleops.local          → công ty slug "internal"
 * - Khách:   {role}@{slug}.saleops.local   → mỗi doanh nghiệp một slug
 */
class TenantEmail
{
    public static function domain(): string
    {
        return (string) (AppSetting::getPlatform('tenant.email_domain')
            ?? config('saleops.tenant.email_domain', 'saleops.local'));
    }

    public static function internalSlug(): string
    {
        return (string) (AppSetting::getPlatform('tenant.internal_slug')
            ?? config('saleops.tenant.internal_slug', 'internal'));
    }

    public static function internalName(): string
    {
        return (string) (AppSetting::getPlatform('tenant.internal_name')
            ?? config('saleops.tenant.internal_name', 'ERM SaleOps (Nội bộ)'));
    }

    public static function defaultPassword(): string
    {
        return (string) (AppSetting::getPlatform('tenant.default_password')
            ?? config('saleops.tenant.default_password', 'password'));
    }

    /** Email thuộc công ty nội bộ (không có slug trong domain). */
    public static function isInternalAddress(string $email): bool
    {
        $domain = self::domain();
        $local = strtolower(trim($email));

        if (! str_ends_with($local, '@'.$domain)) {
            return false;
        }

        $prefix = substr($local, 0, -strlen('@'.$domain));

        // Nội bộ: chỉ 1 phần trước @ (vd admin@saleops.local), không có slug con.
        return ! str_contains($prefix, '.');
    }

    public static function forRole(UserRole $role, Company $company): string
    {
        $local = match ($role) {
            UserRole::Admin => 'admin',
            UserRole::Sales => 'sales',
            UserRole::Marketing => 'marketing',
            UserRole::Warehouse => 'warehouse',
            UserRole::Allocator => 'allocator',
            UserRole::Accounting => 'accounting',
        };

        if ($company->slug === self::internalSlug()) {
            return $local.'@'.self::domain();
        }

        return $local.'@'.$company->slug.'.'.self::domain();
    }

    /** @return list<array{role: string, role_label: string, email: string}> */
    public static function suggestedAccounts(Company $company): array
    {
        return collect(UserRole::cases())
            ->map(fn (UserRole $role) => [
                'role' => $role->value,
                'role_label' => $role->label(),
                'email' => self::forRole($role, $company),
            ])
            ->values()
            ->all();
    }
}
