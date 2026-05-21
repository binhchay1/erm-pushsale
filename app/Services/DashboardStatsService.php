<?php

namespace App\Services;

class DashboardStatsService
{
    /**
     * Demo metrics — thay bằng query thật khi có module Sale/Marketing.
     *
     * @return array<string, mixed>
     */
    public static function adminSnapshot(): array
    {
        $hour = (int) now()->format('G');

        return [
            'revenue_today' => 48_500_000 + ($hour * 1_250_000) + random_int(0, 500_000),
            'orders_closed' => 38 + ($hour % 12) + random_int(0, 5),
            'leads_today' => 128 + random_int(-8, 15),
            'delivery_rate' => round(87.5 + (random_int(-20, 20) / 10), 1),
            'revenue_series' => self::series(7, 35_000_000, 55_000_000),
            'orders_series' => self::series(7, 22, 52),
            'lead_sources' => [
                ['name' => 'Facebook', 'value' => 42 + random_int(0, 5)],
                ['name' => 'Zalo', 'value' => 28 + random_int(0, 4)],
                ['name' => 'Landing', 'value' => 18 + random_int(0, 3)],
                ['name' => 'Khác', 'value' => 12 + random_int(0, 2)],
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function salesSnapshot(): array
    {
        return [
            'leads_pending' => 12 + random_int(0, 4),
            'orders_today' => 5 + random_int(0, 3),
            'reminders' => random_int(0, 4),
            'calls_series' => self::series(7, 8, 28),
            'conversion_series' => self::series(7, 12, 35),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{label: string, value: int|float}>
     */
    private static function series(int $days, int|float $min, int|float $max): array
    {
        $points = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $points[] = [
                'label' => now()->subDays($i)->format('d/m'),
                'value' => random_int((int) $min, (int) $max),
            ];
        }

        return $points;
    }
}
