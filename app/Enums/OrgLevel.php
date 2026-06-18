<?php

namespace App\Enums;

enum OrgLevel: string
{
    case Head = 'head';
    case Supervisor = 'supervisor';
    case Staff = 'staff';

    public function label(): string
    {
        return __('enums.org_level.'.$this->value);
    }
}
