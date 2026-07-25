<?php

namespace App\Support;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\AppSetting;

/**
 * Quyền mặc định theo vai trò (để null permissions vẫn giữ nguyên hành vi cũ,
 * không gây conflict), và danh mục khu vực để hiển thị trong form phân quyền.
 */
final class PermissionCatalog
{
    /**
     * Mức mặc định theo vai trò: area(value) => level(value).
     * Area không liệt kê => None.
     *
     * @return array<string, string>
     */
    public static function defaultsForRole(UserRole $role): array
    {
        $base = self::baseDefaultsForRole($role);
        $raw = AppSetting::getPlatform('role_permission_defaults', '{}') ?: '{}';
        $stored = json_decode($raw, true);

        if (! is_array($stored) || ! is_array($stored[$role->value] ?? null)) {
            return $base;
        }

        $allowed = ['none', 'view', 'full'];
        $override = [];
        foreach (PermissionArea::cases() as $area) {
            $level = (string) ($stored[$role->value][$area->value] ?? '');
            if (in_array($level, $allowed, true)) {
                $override[$area->value] = $level;
            }
        }

        return array_replace($base, $override);
    }

    /** @return array<string, string> */
    public static function baseDefaultsForRole(UserRole $role): array
    {
        $f = PermissionLevel::Full->value;
        $v = PermissionLevel::View->value;

        return match ($role) {
            UserRole::Admin => self::allFull(),
            UserRole::Sales => [
                PermissionArea::Telesale->value => $f,
                PermissionArea::Customers->value => $f,
                PermissionArea::CustomerChat->value => $f,
                PermissionArea::Pancake->value => $v,
                PermissionArea::Reports->value => $v,
            ],
            UserRole::Marketing => [
                PermissionArea::Marketing->value => $f,
                PermissionArea::Leads->value => $v,
                PermissionArea::Customers->value => $v,
                PermissionArea::CustomerChat->value => $v,
                PermissionArea::Pancake->value => $v,
                PermissionArea::Reports->value => $v,
            ],
            UserRole::Warehouse => [
                PermissionArea::Warehouse->value => $f,
                PermissionArea::Shipping->value => $f,
                PermissionArea::Customers->value => $f,
                PermissionArea::CustomerChat->value => $v,
                PermissionArea::Pancake->value => $v,
                PermissionArea::Reports->value => $v,
            ],
            UserRole::Accounting => [
                PermissionArea::Accounting->value => $f,
                PermissionArea::Shipping->value => $v,
                PermissionArea::Customers->value => $v,
                PermissionArea::CustomerChat->value => $v,
                PermissionArea::Pancake->value => $v,
                PermissionArea::Reports->value => $v,
            ],
            UserRole::Allocator => [
                PermissionArea::Leads->value => $f,
                PermissionArea::Customers->value => $v,
                PermissionArea::CustomerChat->value => $v,
                PermissionArea::Pancake->value => $v,
                PermissionArea::Reports->value => $v,
            ],
        };
    }

    /** @return array<string, string> */
    public static function allFull(): array
    {
        $map = [];
        foreach (PermissionArea::cases() as $area) {
            $map[$area->value] = PermissionLevel::Full->value;
        }

        return $map;
    }

    /**
     * Danh mục khu vực để render form: [{key, level_default_for_role}].
     * Trả kèm mức mặc định theo vai trò của user đích để hiển thị gợi ý.
     *
     * @return list<array{key: string}>
     */
    public static function areaList(): array
    {
        return array_map(fn (PermissionArea $a) => ['key' => $a->value], PermissionArea::cases());
    }
}
