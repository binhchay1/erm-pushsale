<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum RankingPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Tuần này',
            self::Month => 'Tháng này',
            self::Quarter => 'Quý này',
        };
    }

    /**
     * Khoảng thời gian [bắt đầu, kết thúc] dùng để lọc doanh số chốt.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $now = Carbon::now();

        return match ($this) {
            self::Week => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            self::Month => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            self::Quarter => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
        };
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Month;
    }
}
