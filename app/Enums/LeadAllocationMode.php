<?php

namespace App\Enums;

enum LeadAllocationMode: string
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return __('enums.lead_allocation_mode.'.$this->value);
    }

    public function isAuto(): bool
    {
        return $this === self::Auto;
    }
}
