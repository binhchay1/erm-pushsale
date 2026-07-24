<?php

namespace App\Services\Finance;

use App\Models\Order;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Support\OrderRevenue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tính chi phí nhân sự theo kế hoạch KPI tháng.
 *
 * - Lương cơ bản được chốt theo ngày công khi kế hoạch đã khóa hoặc đã nhập ngày công.
 * - Kế hoạch chưa khóa và chưa nhập ngày công được xem là chi phí lương dự kiến đủ tháng.
 * - Thưởng là tỷ lệ trên doanh số chốt trong đúng tháng đóng đơn.
 * - Chi phí tháng được phân bổ chính xác tới từng ngày để báo cáo khoảng ngày không bị làm tròn lệch.
 */
final class PayrollCostService
{
    /**
     * @return array{
     *   amount:int,
     *   base_salary:int,
     *   commission:int,
     *   plan_count:int,
     *   estimated_plan_count:int,
     *   daily:list<array{date:string,payroll:int,base_salary:int,commission:int}>
     * }
     */
    public function forRange(CarbonInterface $from, CarbonInterface $to): array
    {
        $rangeFrom = $from->copy()->startOfDay();
        $rangeTo = $to->copy()->endOfDay();
        $plans = $this->plansForRange($rangeFrom, $rangeTo);

        if ($plans->isEmpty()) {
            return [
                'amount' => 0,
                'base_salary' => 0,
                'commission' => 0,
                'plan_count' => 0,
                'estimated_plan_count' => 0,
                'daily' => [],
            ];
        }

        $revenueByPlan = $this->closedRevenueByPlan($plans);
        $daily = collect();
        $estimatedPlanCount = 0;

        foreach ($plans as $plan) {
            $amounts = $this->amounts($plan, (int) ($revenueByPlan[$this->planKey($plan)] ?? 0));
            $estimatedPlanCount += $amounts['estimated'] ? 1 : 0;

            $month = now()->setDate((int) $plan->year, (int) $plan->month, 1)->startOfDay();
            $monthLastDay = $month->copy()->endOfMonth()->startOfDay();
            $overlapFrom = $rangeFrom->greaterThan($month) ? $rangeFrom->copy()->startOfDay() : $month->copy();
            $overlapTo = $rangeTo->lessThan($monthLastDay) ? $rangeTo->copy()->startOfDay() : $monthLastDay;

            if ($overlapTo->lt($overlapFrom)) {
                continue;
            }

            $cursor = $overlapFrom->copy();
            while ($cursor->lte($overlapTo)) {
                $key = $cursor->toDateString();
                $dayIndex = max(0, $cursor->day - 1);
                $baseSalary = $this->allocateDay($amounts['base_salary'], $month->daysInMonth, $dayIndex);
                $commission = $this->allocateDay($amounts['commission'], $month->daysInMonth, $dayIndex);
                $current = $daily->get($key, ['date' => $key, 'payroll' => 0, 'base_salary' => 0, 'commission' => 0]);
                $current['base_salary'] += $baseSalary;
                $current['commission'] += $commission;
                $current['payroll'] += $baseSalary + $commission;
                $daily->put($key, $current);
                $cursor->addDay();
            }
        }

        $rows = $daily->sortKeys()->values();

        return [
            'amount' => (int) $rows->sum('payroll'),
            'base_salary' => (int) $rows->sum('base_salary'),
            'commission' => (int) $rows->sum('commission'),
            'plan_count' => $plans->count(),
            'estimated_plan_count' => $estimatedPlanCount,
            'daily' => $rows->all(),
        ];
    }

    /**
     * Tính đúng số lương/thưởng của một kế hoạch để các màn hình KPI và dashboard dùng chung công thức.
     *
     * @return array{base_salary:int,commission:int,total:int,closed_revenue:int,estimated:bool,payable_days:int,working_days:int}
     */
    public function forPlan(MonthlyKpiPlan $plan): array
    {
        $month = now()->setDate((int) $plan->year, (int) $plan->month, 1)->startOfDay();
        $closedRevenue = (int) Order::query()
            ->where('sale_user_id', $plan->user_id)
            ->whereBetween('closed_at', [$month, $month->copy()->endOfMonth()])
            ->selectRaw('SUM('.OrderRevenue::grossAmountSql().') as aggregate_value')
            ->value('aggregate_value');

        return $this->amounts($plan, $closedRevenue);
    }

    /** @return Collection<int, MonthlyKpiPlan> */
    private function plansForRange(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $monthStart = $from->copy()->startOfMonth();
        $monthEnd = $to->copy()->endOfMonth();

        return MonthlyKpiPlan::query()
            ->where(function (Builder $query) use ($monthStart): void {
                $query->where('year', '>', $monthStart->year)
                    ->orWhere(fn (Builder $q) => $q->where('year', $monthStart->year)->where('month', '>=', $monthStart->month));
            })
            ->where(function (Builder $query) use ($monthEnd): void {
                $query->where('year', '<', $monthEnd->year)
                    ->orWhere(fn (Builder $q) => $q->where('year', $monthEnd->year)->where('month', '<=', $monthEnd->month));
            })
            ->get();
    }

    /** @param Collection<int, MonthlyKpiPlan> $plans
     * @return Collection<string, int>
     */
    private function closedRevenueByPlan(Collection $plans): Collection
    {
        $userIds = $plans->pluck('user_id')->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return collect();
        }

        $first = $plans->sortBy(fn (MonthlyKpiPlan $plan) => sprintf('%04d-%02d', $plan->year, $plan->month))->first();
        $last = $plans->sortByDesc(fn (MonthlyKpiPlan $plan) => sprintf('%04d-%02d', $plan->year, $plan->month))->first();
        $from = now()->setDate((int) $first->year, (int) $first->month, 1)->startOfDay();
        $to = now()->setDate((int) $last->year, (int) $last->month, 1)->endOfMonth();
        $periodExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', orders.closed_at)",
            'pgsql' => "to_char(orders.closed_at, 'YYYY-MM')",
            default => "DATE_FORMAT(orders.closed_at, '%Y-%m')",
        };

        return Order::query()
            ->whereIn('sale_user_id', $userIds)
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw("orders.sale_user_id, {$periodExpression} as payroll_period, SUM(".OrderRevenue::grossAmountSql().') as closed_revenue')
            ->groupBy('orders.sale_user_id')
            ->groupByRaw($periodExpression)
            ->get()
            ->mapWithKeys(fn (Order $row) => [((int) $row->sale_user_id).'|'.((string) $row->payroll_period) => (int) $row->closed_revenue]);
    }

    /**
     * @return array{base_salary:int,commission:int,total:int,closed_revenue:int,estimated:bool,payable_days:int,working_days:int}
     */
    private function amounts(MonthlyKpiPlan $plan, int $closedRevenue): array
    {
        $workingDays = max(1, (int) $plan->working_days);
        $actualDays = max(0, min((int) $plan->actual_days, $workingDays));
        $estimated = ! $plan->locked && $actualDays === 0;
        $payableDays = $estimated ? $workingDays : $actualDays;
        $baseSalary = (int) round(((int) $plan->base_salary * $payableDays) / $workingDays);
        $bonusRule = $this->revenueBonusRule($plan, $closedRevenue);
        $commission = $bonusRule
            ? ((int) $bonusRule->bonus_amount > 0
                ? (int) $bonusRule->bonus_amount
                : (int) round(max(0, $closedRevenue) * ((float) $bonusRule->bonus_percent / 100)))
            : (int) round(max(0, $closedRevenue) * ((float) $plan->bonus_percent / 100));

        return [
            'base_salary' => max(0, $baseSalary),
            'commission' => max(0, $commission),
            'total' => max(0, $baseSalary) + max(0, $commission),
            'closed_revenue' => max(0, $closedRevenue),
            'estimated' => $estimated,
            'payable_days' => $payableDays,
            'working_days' => $workingDays,
        ];
    }


    private function revenueBonusRule(MonthlyKpiPlan $plan, int $closedRevenue): ?\App\Models\Pushsale\RevenueBonusRule
    {
        $user = $plan->relationLoaded('user') ? $plan->user : $plan->user()->first(['id', 'role']);
        $role = $user?->role;
        $roleValue = $role instanceof \BackedEnum ? (string) $role->value : (string) $role;
        $position = str_contains($roleValue, 'sale') ? 'sales' : 'marketing';

        return \App\Models\Pushsale\RevenueBonusRule::query()
            ->where('position_key', $position)
            ->where('year', (int) $plan->year)
            ->where('month', (int) $plan->month)
            ->where('revenue_from', '<=', max(0, $closedRevenue))
            ->where(function ($query) use ($closedRevenue): void {
                $query->where('revenue_to', 0)->orWhere('revenue_to', '>', max(0, $closedRevenue));
            })
            ->orderByDesc('revenue_from')
            ->orderBy('sort_order')
            ->first();
    }

    private function allocateDay(int $total, int $daysInMonth, int $dayIndex): int
    {
        $days = max(1, $daysInMonth);
        $base = intdiv(max(0, $total), $days);
        $remainder = max(0, $total) % $days;

        return $base + ($dayIndex < $remainder ? 1 : 0);
    }

    private function planKey(MonthlyKpiPlan $plan): string
    {
        return ((int) $plan->user_id).'|'.sprintf('%04d-%02d', $plan->year, $plan->month);
    }
}
