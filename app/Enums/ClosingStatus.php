<?php

namespace App\Enums;

enum ClosingStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::Closed => 'Đã chốt',
            self::Cancelled => 'Đã hủy / bỏ',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->all();
    }
}
