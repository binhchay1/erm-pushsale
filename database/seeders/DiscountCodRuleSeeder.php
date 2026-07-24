<?php

namespace Database\Seeders;

use App\Models\Pushsale\DiscountCodRule;
use Illuminate\Database\Seeder;

class DiscountCodRuleSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [
            ['order_from' => 0, 'discount_value' => 0, 'calculation_type' => 'fixed'],
            ['order_from' => 500_000, 'discount_value' => 20_000, 'calculation_type' => 'fixed'],
            ['order_from' => 1_000_000, 'discount_value' => 5, 'calculation_type' => 'percent'],
        ];

        foreach ($discounts as $row) {
            DiscountCodRule::query()->updateOrCreate(
                ['rule_type' => 'discount', 'order_from' => $row['order_from']],
                $row + ['rule_type' => 'discount', 'is_active' => true],
            );
        }

        $codFees = [
            ['order_from' => 0, 'discount_value' => 30_000],
            ['order_from' => 500_000, 'discount_value' => 20_000],
            ['order_from' => 1_000_000, 'discount_value' => 0],
        ];

        foreach ($codFees as $row) {
            DiscountCodRule::query()->updateOrCreate(
                ['rule_type' => 'cod', 'order_from' => $row['order_from']],
                [
                    'rule_type' => 'cod',
                    'order_from' => $row['order_from'],
                    'discount_value' => $row['discount_value'],
                    'calculation_type' => 'fixed',
                    'cod_from' => $row['order_from'],
                    'cod_to' => $row['discount_value'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Đã tạo cấu hình chiết khấu và COD demo.');
    }
}
