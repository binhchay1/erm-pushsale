<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Marketing = 'marketing';
    case Warehouse = 'warehouse';
    case Allocator = 'allocator';
    case Accounting = 'accounting';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị',
            self::Sales => 'Telesale',
            self::Marketing => 'Marketing',
            self::Warehouse => 'Kho',
            self::Allocator => 'Chia số',
            self::Accounting => 'Kế toán',
        };
    }
}
