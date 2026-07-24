<?php

namespace Database\Seeders;

use App\Models\Pushsale\AnnualBusinessPlanMetric;
use Illuminate\Database\Seeder;

class AnnualBusinessPlanSeeder extends Seeder
{
    public function run(): void
    {
        $years = [now()->year - 1, now()->year, now()->year + 1];
        $definitions = AnnualBusinessPlanMetric::metricDefinitions();

        foreach ($years as $yearIndex => $year) {
            foreach (range(1, 12) as $month) {
                $growth = 1 + ($yearIndex * 0.08) + ($month - 1) * 0.035;
                $values = AnnualBusinessPlanMetric::plannedValuesFromInput([
                    'contacts' => round(1200 * $growth + $month * 55),
                    'close_rate' => 28 + ($month % 5),
                    'products_per_order' => 1.55 + (($month % 4) * 0.08),
                    'unit_price' => 520000 + $month * 18000,
                    'contact_price' => 36000 + ($month % 6) * 2500,
                    'marketing_salary' => 45000000 + $month * 1500000,
                    'marketing_bonus' => 22000000 + $month * 900000,
                    'sale_salary' => 80000000 + $month * 1800000,
                    'sale_bonus' => 36000000 + $month * 1200000,
                    'other_cost' => 18000000 + $month * 750000,
                    'cost_of_goods_percent' => 34 + ($month % 4),
                ]);

                foreach ($definitions as $code => $definition) {
                    AnnualBusinessPlanMetric::query()->updateOrCreate(
                        ['year' => $year, 'month' => $month, 'metric_code' => (string) $code],
                        [
                            'metric_name' => $definition['label'],
                            'planned_value' => $values[(string) $code] ?? 0,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Đã tạo dữ liệu demo kế hoạch kinh doanh năm cho CEO 7.1.2.');
    }
}
