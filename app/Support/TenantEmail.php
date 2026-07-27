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

        return self::build($local, $company);
    }

    /** Hậu tố email cố định theo doanh nghiệp: @saleops.local hoặc @{slug}.saleops.local */
    public static function suffixFor(Company $company): string
    {
        return '@'.self::hostFor($company);
    }

    public static function hostFor(Company $company): string
    {
        $override = strtolower(ltrim(trim((string) ($company->email_login_host ?? '')), '@'));
        if ($override !== '') {
            return $override;
        }

        $domain = self::domain();

        if ($company->slug === self::internalSlug()) {
            return $domain;
        }

        return $company->slug.'.'.$domain;
    }

    public static function normalizeLocalPart(string $local): string
    {
        $local = strtolower(trim($local));
        $local = preg_replace('/[^a-z0-9._-]+/', '', $local) ?? '';
        $local = preg_replace('/\.{2,}/', '.', $local) ?? '';

        return trim($local, '.-_');
    }

    public static function build(string $localPart, Company $company): string
    {
        $local = self::normalizeLocalPart($localPart);

        if ($local === '') {
            return '';
        }

        return $local.self::suffixFor($company);
    }

    public static function localPartFromEmail(string $email, Company $company): ?string
    {
        $email = strtolower(trim($email));
        $suffix = self::suffixFor($company);

        if (! str_ends_with($email, $suffix)) {
            return null;
        }

        $local = substr($email, 0, -strlen($suffix));

        return $local !== '' ? $local : null;
    }

    public static function acceptsForCompany(string $email, Company $company): bool
    {
        $local = self::localPartFromEmail($email, $company);

        if ($local === null) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/', $local);
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
