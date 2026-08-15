<?php

namespace App\Enums;

enum ClosingStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.closing_status.'.$this->value);
    }

    /**
     * Bộ lọc trạng thái chốt đơn (feedback): chỉ Đã chốt / Chưa chốt.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Closed->value, 'label' => __('enums.closing_status.closed')],
            ['value' => self::Open->value, 'label' => __('enums.closing_status.open')],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function allOptions(): array
    {
        return collect(self::cases())
            ->map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->all();
    }
}
