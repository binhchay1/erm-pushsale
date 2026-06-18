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
        return __('enums.user_role.'.$this->value);
    }
}
