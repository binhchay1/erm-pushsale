<?php

namespace App\Enums;

enum RankingDepartment: string
{
    case Sales = 'sales';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Telesale',
            self::Marketing => 'Marketing',
        };
    }

    public function role(): UserRole
    {
        return match ($this) {
            self::Sales => UserRole::Sales,
            self::Marketing => UserRole::Marketing,
        };
    }

    public static function forUserRole(UserRole $role): ?self
    {
        return match ($role) {
            UserRole::Sales => self::Sales,
            UserRole::Marketing => self::Marketing,
            default => null,
        };
    }

    /** Cột trên bảng orders dùng để quy doanh số về nhân sự. */
    public function orderColumn(): string
    {
        return match ($this) {
            self::Sales => 'sale_user_id',
            self::Marketing => 'marketer_user_id',
        };
    }
}
