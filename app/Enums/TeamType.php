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
        return __('enums.team_type.'.$this->value);
    }
}
