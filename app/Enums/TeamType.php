<?php

namespace App\Enums;

enum TeamType: string
{
    case Sale = 'sale';
    case Marketing = 'marketing';
    case Warehouse = 'warehouse';
    case Allocator = 'allocator';
    case Accounting = 'accounting';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Telesale',
            self::Marketing => 'Marketing',
            self::Warehouse => 'Kho',
            self::Allocator => 'Chia số',
            self::Accounting => 'Kế toán',
        };
    }
}
