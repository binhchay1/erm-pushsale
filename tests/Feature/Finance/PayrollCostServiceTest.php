<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use App\Services\Finance\PayrollCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayrollCostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_month_uses_attendance_and_closed_revenue_for_payroll(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $plan = MonthlyKpiPlan::query()->create([
            'user_id' => $sale->id,
            'year' => 2026,
            'month' => 7,
            'kpi_name' => 'KPI Sale tháng 7',
            'base_salary' => 13_000_000,
            'bonus_percent' => 5,
            'working_days' => 26,
            'actual_days' => 13,
            'locked' => true,
            'created_by_user_id' => $sale->id,
            'updated_by_user_id' => $sale->id,
        ]);

        $this->order($sale, '2026-07-08 10:00:00', 20_000_000);
        $this->order($sale, '2026-08-01 10:00:00', 99_000_000);

        $amounts = app(PayrollCostService::class)->forPlan($plan);

        $this->assertSame(6_500_000, $amounts['base_salary']);
        $this->assertSame(1_000_000, $amounts['commission']);
        $this->assertSame(7_500_000, $amounts['total']);
        $this->assertSame(20_000_000, $amounts['closed_revenue']);
        $this->assertFalse($amounts['estimated']);
    }

    public function test_open_plan_without_attendance_is_estimated_and_daily_allocation_is_exact(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        MonthlyKpiPlan::query()->create([
            'user_id' => $sale->id,
            'year' => 2026,
            'month' => 7,
            'kpi_name' => 'KPI đang mở',
            'base_salary' => 31,
            'bonus_percent' => 0,
            'working_days' => 26,
            'actual_days' => 0,
            'locked' => false,
            'created_by_user_id' => $sale->id,
            'updated_by_user_id' => $sale->id,
        ]);

        $service = app(PayrollCostService::class);
        $full = $service->forRange(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));
        $partial = $service->forRange(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-10'));

        $this->assertSame(31, $full['amount']);
        $this->assertSame(31, $full['base_salary']);
        $this->assertSame(1, $full['estimated_plan_count']);
        $this->assertCount(31, $full['daily']);
        $this->assertSame(10, $partial['amount']);
    }

    private function order(User $sale, string $closedAt, int $total): Order
    {
        return Order::query()->create([
            'order_code' => 'PAY-'.str_replace([' ', ':', '-'], '', $closedAt),
            'sale_user_id' => $sale->id,
            'customer_name' => 'Khách tính lương',
            'customer_phone' => '0900000000',
            'closed_at' => $closedAt,
            'delivery_status' => 'confirmed',
            'total' => $total,
            'data_arrived_at' => $closedAt,
        ]);
    }
}
