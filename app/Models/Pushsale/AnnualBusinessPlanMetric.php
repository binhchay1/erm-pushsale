<?php

namespace App\Models\Pushsale;

class AnnualBusinessPlanMetric extends BusinessRecord
{
    protected $table = 'annual_business_plan_metrics';

    protected $fillable = [
        'year',
        'month',
        'metric_code',
        'metric_name',
        'planned_value',
        'locked',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'planned_value' => 'decimal:2',
            'locked' => 'boolean',
        ];
    }

    /** @return array<string, array{label:string,symbol:string,format:string}> */
    public static function metricDefinitions(): array
    {
        return [
            '1' => ['label' => 'Doanh số (1) - Lên đơn là tính', 'symbol' => '(1)', 'format' => 'currency'],
            '2' => ['label' => 'Số đơn chốt', 'symbol' => '(2)', 'format' => 'number'],
            '3' => ['label' => 'Số contact (3)', 'symbol' => '(3)', 'format' => 'number'],
            '4' => ['label' => 'Tỉ lệ chốt', 'symbol' => '(4)', 'format' => 'percent'],
            '5' => ['label' => 'Giá trị đơn trung bình', 'symbol' => '(5)', 'format' => 'currency'],
            '6' => ['label' => 'Số sản phẩm/đơn', 'symbol' => '(6)', 'format' => 'number'],
            '7' => ['label' => 'Đơn giá trung bình/sản phẩm', 'symbol' => '(7)', 'format' => 'currency'],
            '8' => ['label' => 'Chi phí', 'symbol' => '(8)', 'format' => 'currency'],
            '9' => ['label' => 'Ngân sách marketing', 'symbol' => '(9)', 'format' => 'currency'],
            '10' => ['label' => 'Tỉ lệ ngân sách/doanh số', 'symbol' => '(10)', 'format' => 'percent'],
            '11' => ['label' => 'Giá contact', 'symbol' => '(11)', 'format' => 'currency'],
            '12' => ['label' => 'Lương marketing', 'symbol' => '(12)', 'format' => 'currency'],
            '13' => ['label' => 'Thưởng marketing', 'symbol' => '(13)', 'format' => 'currency'],
            '14' => ['label' => 'Lương sale', 'symbol' => '(14)', 'format' => 'currency'],
            '15' => ['label' => 'Thưởng sale', 'symbol' => '(15)', 'format' => 'currency'],
            '16' => ['label' => 'Chi phí khác', 'symbol' => '(16)', 'format' => 'currency'],
            '17' => ['label' => 'Giá vốn hàng hóa', 'symbol' => '(17)', 'format' => 'percent'],
            '18' => ['label' => 'Lợi nhuận', 'symbol' => '(18)', 'format' => 'currency'],
        ];
    }

    /** @return array<int, array<string, string>> */
    public static function formulaRows(): array
    {
        return [
            ['metric' => 'Giá trị đơn trung bình (5)', 'formula' => '5 = (6 * 7)', 'description' => 'Giá trị đơn trung bình (5) = Số sản phẩm/đơn (6) * Đơn giá trung bình/sản phẩm (7)'],
            ['metric' => 'Số đơn chốt (2)', 'formula' => '2 = (3 * 4) / 100', 'description' => 'Số đơn chốt (2) = (Số contact (3) * Tỉ lệ chốt (4)) /100'],
            ['metric' => 'Doanh số tổng - Lên đơn là tính(1)', 'formula' => '1 = 2 * 5', 'description' => 'Doanh số tổng - Lên đơn là tính(1) = Số đơn chốt (2) * Giá trị đơn trung bình (5)'],
            ['metric' => 'Ngân sách marketing (9)', 'formula' => '9 = 3 * 11', 'description' => 'Ngân sách marketing (9) = Số contact (3) * Giá contact (11)'],
            ['metric' => 'Tỉ lệ ngân sách/doanh số (10)', 'formula' => '10 = (9 / 1) * 100', 'description' => 'Tỉ lệ ngân sách/doanh số (10)= (Ngân sách marketing (9) / Doanh số (1)) * 100'],
            ['metric' => 'Chi phí (8)', 'formula' => '8 = 9 + 12 + 13 + 14 + 15 + 16 + (17 * 1)/100', 'description' => 'Chi phí (8) = Ngân sách marketing (9) + Lương marketing (12) + Thưởng marketing (13) + Lương sale (14) + Thưởng sale (15) + Chi phí khác (16) + (Giá vốn hàng hóa (17) * Doanh số tổng - Lên đơn là tính(1))/100'],
            ['metric' => 'Lợi nhuận (18)', 'formula' => '18 = 1 - 8', 'description' => 'Lợi nhuận (18) = Doanh số tổng - Lên đơn là tính(1) - Chi phí (8)'],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, float> */
    public static function plannedValuesFromInput(array $input): array
    {
        $contacts = max(0.0, (float) ($input['contacts'] ?? 0));
        $closeRate = max(0.0, (float) ($input['close_rate'] ?? 0));
        $productsPerOrder = max(0.0, (float) ($input['products_per_order'] ?? 0));
        $unitPrice = max(0.0, (float) ($input['unit_price'] ?? 0));
        $contactPrice = max(0.0, (float) ($input['contact_price'] ?? 0));
        $marketingSalary = max(0.0, (float) ($input['marketing_salary'] ?? 0));
        $marketingBonus = max(0.0, (float) ($input['marketing_bonus'] ?? 0));
        $saleSalary = max(0.0, (float) ($input['sale_salary'] ?? 0));
        $saleBonus = max(0.0, (float) ($input['sale_bonus'] ?? 0));
        $otherCost = max(0.0, (float) ($input['other_cost'] ?? 0));
        $costOfGoodsPercent = max(0.0, (float) ($input['cost_of_goods_percent'] ?? 0));

        $avgOrderValue = $productsPerOrder * $unitPrice;
        $closedOrders = $contacts * $closeRate / 100;
        $revenue = $closedOrders * $avgOrderValue;
        $marketingBudget = $contacts * $contactPrice;
        $budgetRate = $revenue > 0 ? $marketingBudget / $revenue * 100 : 0;
        $cost = $marketingBudget + $marketingSalary + $marketingBonus + $saleSalary + $saleBonus + $otherCost + ($costOfGoodsPercent * $revenue / 100);
        $profit = $revenue - $cost;

        return [
            '1' => round($revenue, 2),
            '2' => round($closedOrders, 2),
            '3' => round($contacts, 2),
            '4' => round($closeRate, 2),
            '5' => round($avgOrderValue, 2),
            '6' => round($productsPerOrder, 2),
            '7' => round($unitPrice, 2),
            '8' => round($cost, 2),
            '9' => round($marketingBudget, 2),
            '10' => round($budgetRate, 2),
            '11' => round($contactPrice, 2),
            '12' => round($marketingSalary, 2),
            '13' => round($marketingBonus, 2),
            '14' => round($saleSalary, 2),
            '15' => round($saleBonus, 2),
            '16' => round($otherCost, 2),
            '17' => round($costOfGoodsPercent, 2),
            '18' => round($profit, 2),
        ];
    }
}
