<?php

namespace Tests\Feature\Pushsale;

use App\Models\Order;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\Pushsale\RevenueBonusRule;
use App\Models\User;
use App\Services\Finance\PayrollCostService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RevenueBonusRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoRevenueBonusRulePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_bonus_rule_seed_creates_marketing_and_sales_ranges(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(RevenueBonusRuleSeeder::class);

        $this->assertGreaterThanOrEqual(30, RevenueBonusRule::query()->where('position_key', 'marketing')->count());
        $this->assertGreaterThanOrEqual(30, RevenueBonusRule::query()->where('position_key', 'sales')->count());
        $this->assertGreaterThan(0, RevenueBonusRule::query()->where('position_key', 'sales')->sum('bonus_amount'));
    }

    public function test_bonus_rules_are_business_input_for_payroll_commission(): void
    {
        $this->seed(AccountSeeder::class);

        $sale = User::query()->where('role', 'sales')->firstOrFail();
        RevenueBonusRule::query()->create([
            'position_key' => 'sales',
            'year' => now()->year,
            'month' => now()->month,
            'revenue_from' => 0,
            'revenue_to' => 100000000,
            'bonus_percent' => 2,
            'bonus_amount' => 0,
        ]);

        $plan = MonthlyKpiPlan::query()->create([
            'user_id' => $sale->id,
            'year' => now()->year,
            'month' => now()->month,
            'kpi_name' => 'KPI Sale test',
            'bonus_percent' => 0,
            'base_salary' => 10000000,
            'working_days' => 26,
            'actual_days' => 26,
            'locked' => true,
        ]);

        Order::query()->create([
            'customer_name' => 'Khách test',
            'customer_phone' => '0987654321',
            'sale_user_id' => $sale->id,
            'closing_status' => 'closed',
            'subtotal' => 50000000,
            'total' => 50000000,
            'closed_at' => now(),
        ]);

        $payroll = app(PayrollCostService::class)->forPlan($plan);

        $this->assertSame(1000000, $payroll['commission']);
    }
}
