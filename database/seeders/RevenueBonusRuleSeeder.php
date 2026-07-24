<?php

namespace Database\Seeders;

use App\Models\Pushsale\RevenueBonusRule;
use Illuminate\Database\Seeder;

final class RevenueBonusRuleSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;
        $ranges = [
            ['from' => 0, 'to' => 50000000, 'percent' => 0.50, 'amount' => 0],
            ['from' => 50000000, 'to' => 100000000, 'percent' => 0.75, 'amount' => 250000],
            ['from' => 100000000, 'to' => 200000000, 'percent' => 1.00, 'amount' => 500000],
            ['from' => 200000000, 'to' => 350000000, 'percent' => 1.25, 'amount' => 1000000],
            ['from' => 350000000, 'to' => 0, 'percent' => 1.50, 'amount' => 1500000],
        ];

        foreach ([$year - 1, $year, $year + 1] as $targetYear) {
            foreach (range(1, 12) as $month) {
                foreach (['marketing', 'sales'] as $position) {
                    foreach ($ranges as $index => $range) {
                        $factor = $position === 'sales' ? 1.15 : 1.0;
                        RevenueBonusRule::query()->updateOrCreate(
                            [
                                'position_key' => $position,
                                'year' => $targetYear,
                                'month' => $month,
                                'revenue_from' => (int) round($range['from'] * $factor),
                            ],
                            [
                                'revenue_to' => (int) round($range['to'] * $factor),
                                'bonus_percent' => $range['percent'] + ($position === 'sales' ? 0.25 : 0),
                                'bonus_amount' => (int) round($range['amount'] * $factor),
                                'locked' => $targetYear < $year || ($targetYear === $year && $month < now()->month),
                                'sort_order' => $index + 1,
                            ]
                        );
                    }
                }
            }
        }
    }
}
