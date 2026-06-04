<?php

namespace App\Enums;

enum OrgLevel: string
{
    case Head = 'head';
    case Supervisor = 'supervisor';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Head => 'Trưởng ban / nhóm',
            self::Supervisor => 'Giám sát',
            self::Staff => 'Nhân viên',
        };
    }
}
