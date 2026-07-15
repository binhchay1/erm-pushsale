<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Models\LandingConnection;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pushsale\Expense;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\Finance\PayrollCostService;
use App\Services\Marketing\MarketingBudgetService;
use App\Support\OrderRevenue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bảng điều hành tài chính dành cho Admin.
 *
 * Mọi giá trị tiền là số nguyên VND. Service phân biệt rõ:
 * - doanh số đã chốt (booked),
 * - doanh thu đã ghi nhận (delivered/paid),
 * - tiền đã thu,
 * - chi marketing, giá vốn, vận chuyển và chi phí vận hành.
 */
final class AdminFinancialDashboardService
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly MarketingBudgetService $budgets,
        private readonly PayrollCostService $payroll,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(User $user, ReportFilterData $filter): array
    {
        $from = ($filter->dateFrom ?? now())->copy()->startOfDay();
        $to = ($filter->dateTo ?? now())->copy()->endOfDay();
        $orders = $this->queries->orders($user, $filter);
        $eligible = (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible());
        $closed = (clone $orders)->whereNotNull('closed_at');
        $grossSql = OrderRevenue::grossAmountSql();
        $shippingSql = OrderRevenue::shippingCostSql();

        $bookedRevenue = $this->sumExpression($closed, $grossSql);
        $recognizedRevenue = $this->sumExpression($eligible, $grossSql);
        $shippingCost = $this->sumExpression($eligible, $shippingSql);
        $cogs = $this->costOfGoods($eligible);
        $cashCollected = $this->cashCollected($closed);
        $outstandingCod = $this->outstandingCod($orders);
        $operatingExpenses = $this->operatingExpenses($from, $to);
        $payroll = $this->payroll->forRange($from, $to);

        $connections = $this->connections($filter);
        $sourceIds = $connections->pluck('marketing_source_id')->filter()->unique()->values();
        $marketing = $this->budgets->effectiveForSourceIds($sourceIds, $from, $to);

        $grossProfit = $recognizedRevenue - $cogs - $shippingCost - $marketing['amount'];
        $netProfit = $grossProfit - $operatingExpenses - (int) $payroll['amount'];
        $ordersCount = (clone $orders)->count();
        $closedCount = (clone $closed)->count();
        $eligibleCount = (clone $eligible)->count();
        $leadCount = $this->queries->leads($user, $filter)->count();

        $landingRows = $this->landingBudgetRows($connections, $orders, $from, $to);
        $daily = $this->dailyCashFlow($closed, $eligible, $sourceIds, $from, $to, $filter, $payroll['daily']);
        $inventoryValue = $this->inventoryValue();
        $lowStock = WarehouseInventory::query()
            ->where('is_discontinued', false)
            ->where('business_status', 'active')
            ->where('stock_quantity', '<', 10)
            ->count();
        $returned = (clone $orders)->whereIn('delivery_status', ['returned', 'returning', 'refund'])->count();
        $cancelled = (clone $orders)->whereIn('delivery_status', ['cancel_waybill', 'cancel_closing', 'cannot_deliver'])->count();

        return [
            'financial' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'currency' => 'VND',
                'booked_revenue' => $bookedRevenue,
                'recognized_revenue' => $recognizedRevenue,
                'cash_collected' => $cashCollected,
                'outstanding_cod' => $outstandingCod,
                'marketing_spend' => (int) $marketing['amount'],
                'marketing_actual' => (int) $marketing['actual'],
                'marketing_planned' => (int) $marketing['planned'],
                'marketing_basis' => $marketing['basis'],
                'cogs' => $cogs,
                'shipping_cost' => $shippingCost,
                'operating_expenses' => $operatingExpenses,
                'payroll_cost' => (int) $payroll['amount'],
                'payroll_base_salary' => (int) $payroll['base_salary'],
                'payroll_commission' => (int) $payroll['commission'],
                'payroll_plan_count' => (int) $payroll['plan_count'],
                'payroll_estimated_plan_count' => (int) $payroll['estimated_plan_count'],
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'inventory_value' => $inventoryValue,
                'leads' => $leadCount,
                'orders' => $ordersCount,
                'closed_orders' => $closedCount,
                'recognized_orders' => $eligibleCount,
                'returned_orders' => $returned,
                'cancelled_orders' => $cancelled,
                'conversion_rate' => $this->percentage($closedCount, max(1, $leadCount)),
                'delivery_rate' => $this->percentage($eligibleCount, max(1, $closedCount)),
                'aov' => $eligibleCount > 0 ? (int) round($recognizedRevenue / $eligibleCount) : 0,
                'roas' => $marketing['amount'] > 0 ? round($recognizedRevenue / $marketing['amount'], 2) : 0,
                'cash_collection_rate' => $bookedRevenue > 0 ? round(($cashCollected / $bookedRevenue) * 100, 1) : 0,
                'low_stock_items' => $lowStock,
            ],
            'cash_flow_series' => $daily,
            'landing_budget_rows' => $landingRows,
            'financial_alerts' => $this->alerts(
                $netProfit,
                $outstandingCod,
                $landingRows,
                $lowStock,
                $marketing['basis'],
                (int) $payroll['amount'],
                (int) $payroll['estimated_plan_count'],
                $from,
                $to,
            ),
        ];
    }

    /** @param Builder<Order> $query */
    private function sumExpression(Builder $query, string $expression): int
    {
        return (int) ((clone $query)->selectRaw("SUM({$expression}) as aggregate_value")->value('aggregate_value') ?? 0);
    }

    /** @param Builder<Order> $eligible */
    private function costOfGoods(Builder $eligible): int
    {
        $orderIds = (clone $eligible)->select('orders.id');

        return (int) OrderItem::query()
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->selectRaw('SUM(order_items.quantity * '.$this->costPriceSql().') as aggregate_value')
            ->value('aggregate_value');
    }

    /** @param Builder<Order> $closed */
    private function cashCollected(Builder $closed): int
    {
        $gross = OrderRevenue::grossAmountSql();
        $received = '(COALESCE(orders.deposit, 0) + COALESCE(orders.settled_cod_amount, 0))';
        $expression = "CASE WHEN {$received} > {$gross} THEN {$gross} ELSE {$received} END";

        return $this->sumExpression($closed, $expression);
    }

    /** @param Builder<Order> $orders */
    private function outstandingCod(Builder $orders): int
    {
        $expression = 'CASE WHEN COALESCE(orders.amount_to_collect, 0) > COALESCE(orders.settled_cod_amount, 0) '
            .'THEN COALESCE(orders.amount_to_collect, 0) - COALESCE(orders.settled_cod_amount, 0) ELSE 0 END';

        return $this->sumExpression(
            (clone $orders)->whereNotIn('delivery_status', ['cancel_waybill', 'cancel_closing', 'returned', 'refund']),
            $expression,
        );
    }

    /** @return Collection<int, LandingConnection> */
    private function connections(ReportFilterData $filter): Collection
    {
        return LandingConnection::query()
            ->with(['marketer:id,name', 'marketingSource:id,name,ad_channel,utm_source,utm_campaign'])
            ->when($filter->marketingSourceId, fn (Builder $q) => $q->where('marketing_source_id', $filter->marketingSourceId))
            ->when($filter->marketerId, fn (Builder $q) => $q->where('marketer_user_id', $filter->marketerId))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param Collection<int, LandingConnection> $connections
     * @param Builder<Order> $orders
     * @return list<array<string, mixed>>
     */
    private function landingBudgetRows(Collection $connections, Builder $orders, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($connections->isEmpty()) {
            return [];
        }

        $connectionIds = $connections->pluck('id');
        $leadCounts = LeadIngestion::query()
            ->whereIn('landing_connection_id', $connectionIds)
            ->where('counts_as_lead', true)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('landing_connection_id, COUNT(*) as aggregate_value')
            ->groupBy('landing_connection_id')
            ->pluck('aggregate_value', 'landing_connection_id');

        $orderStats = (clone $orders)
            ->whereIn('landing_connection_id', $connectionIds)
            ->selectRaw('landing_connection_id, COUNT(*) as orders_count, SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END) as closed_count')
            ->groupBy('landing_connection_id')
            ->get()
            ->keyBy('landing_connection_id');

        $revenueStats = (clone $orders)
            ->whereIn('landing_connection_id', $connectionIds)
            ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
            ->selectRaw('landing_connection_id, SUM('.OrderRevenue::grossAmountSql().') as revenue_total')
            ->groupBy('landing_connection_id')
            ->pluck('revenue_total', 'landing_connection_id');

        return $connections->map(function (LandingConnection $connection) use ($leadCounts, $orderStats, $revenueStats, $from, $to): array {
            $sourceIds = collect([$connection->marketing_source_id])->filter();
            $spend = $this->budgets->effectiveForSourceIds($sourceIds, $from, $to);
            $planned = $this->budgets->plannedForRange($connection, $from, $to);
            $leads = (int) ($leadCounts[$connection->id] ?? 0);
            $stats = $orderStats->get($connection->id);
            $orders = (int) ($stats?->orders_count ?? 0);
            $closed = (int) ($stats?->closed_count ?? 0);
            $revenue = (int) ($revenueStats[$connection->id] ?? 0);
            $effective = (int) $spend['amount'];

            return [
                'id' => $connection->id,
                'name' => $connection->name,
                'marketer' => $connection->marketer?->name ?? '—',
                'channel' => $connection->ad_channel ?: ($connection->marketingSource?->ad_channel ?: 'landing'),
                'period_from' => $connection->budget_start_date?->toDateString(),
                'period_to' => $connection->budget_end_date?->toDateString(),
                'planned' => $planned,
                'actual' => (int) $spend['actual'],
                'effective' => $effective,
                'basis' => $spend['basis'],
                // Còn lại = kế hoạch trừ thực chi đã nhập; effective chỉ là chi phí kế toán fallback.
                'remaining' => $planned - (int) $spend['actual'],
                'utilization_rate' => $planned > 0 ? round(((int) $spend['actual'] / $planned) * 100, 1) : 0,
                'leads' => $leads,
                'orders' => $orders,
                'closed_orders' => $closed,
                'revenue' => $revenue,
                'cpl' => $leads > 0 ? (int) round($effective / $leads) : 0,
                'cpa' => $closed > 0 ? (int) round($effective / $closed) : 0,
                'roas' => $effective > 0 ? round($revenue / $effective, 2) : 0,
                'is_active' => (bool) $connection->is_active,
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    /**
     * @param Builder<Order> $closed
     * @param Builder<Order> $eligible
     * @param Collection<int, int> $sourceIds
     * @return list<array<string, int|string>>
     */
    private function dailyCashFlow(Builder $closed, Builder $eligible, Collection $sourceIds, CarbonInterface $from, CarbonInterface $to, ReportFilterData $filter, array $payrollDaily): array
    {
        $days = min(31, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $seriesTo = $from->copy()->startOfDay()->addDays($days - 1)->endOfDay();
        $dateColumn = $this->queries->dateColumn($filter);
        $gross = OrderRevenue::grossAmountSql();
        $shipping = OrderRevenue::shippingCostSql();

        $moneyByDay = (clone $eligible)
            ->whereBetween($dateColumn, [$from, $seriesTo])
            ->selectRaw("DATE({$dateColumn}) as metric_day, SUM({$gross}) as revenue, SUM({$shipping}) as shipping")
            ->groupByRaw("DATE({$dateColumn})")
            ->get()
            ->keyBy('metric_day');

        $received = '(COALESCE(orders.deposit, 0) + COALESCE(orders.settled_cod_amount, 0))';
        $cashExpression = "CASE WHEN {$received} > {$gross} THEN {$gross} ELSE {$received} END";
        $cashByDay = (clone $closed)
            ->whereBetween($dateColumn, [$from, $seriesTo])
            ->selectRaw("DATE({$dateColumn}) as metric_day, SUM({$cashExpression}) as aggregate_value")
            ->groupByRaw("DATE({$dateColumn})")
            ->pluck('aggregate_value', 'metric_day');

        $eligibleIds = (clone $eligible)->whereBetween($dateColumn, [$from, $seriesTo])->select('orders.id');
        $cogsByDay = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $eligibleIds)
            ->selectRaw("DATE(orders.{$dateColumn}) as metric_day, SUM(order_items.quantity * ".$this->costPriceSql().") as aggregate_value")
            ->groupByRaw("DATE(orders.{$dateColumn})")
            ->pluck('aggregate_value', 'metric_day');

        $marketingByDay = collect($this->budgets->dailySeriesForSourceIds($sourceIds, $from, $seriesTo))->keyBy('date');
        $expenseByDay = $this->dailyOperatingExpenses($from, $seriesTo);
        $payrollByDay = collect($payrollDaily)->keyBy('date');
        $rows = [];
        $cursor = $from->copy()->startOfDay();

        for ($offset = 0; $offset < $days; $offset++, $cursor->addDay()) {
            $key = $cursor->toDateString();
            $money = $moneyByDay->get($key);
            $revenue = (int) ($money?->revenue ?? 0);
            $shippingCost = (int) ($money?->shipping ?? 0);
            $cash = (int) ($cashByDay[$key] ?? 0);
            $cogs = (int) ($cogsByDay[$key] ?? 0);
            $marketing = (int) ($marketingByDay->get($key)['effective'] ?? 0);
            $expense = (int) ($expenseByDay[$key] ?? 0);
            $payroll = (int) ($payrollByDay->get($key)['payroll'] ?? 0);

            $rows[] = [
                'date' => $key,
                'label' => $cursor->format('d/m'),
                'revenue' => $revenue,
                'cash_collected' => $cash,
                'marketing' => $marketing,
                'cogs' => $cogs,
                'shipping' => $shippingCost,
                'payroll' => $payroll,
                'expenses' => $expense,
                'net_profit' => $revenue - $marketing - $cogs - $shippingCost - $payroll - $expense,
            ];
        }

        return $rows;
    }

    private function operatingExpenses(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $this->dailyOperatingExpenses($from, $to)->sum();
    }

    /** @return Collection<string, int> */
    private function dailyOperatingExpenses(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $totals = collect();

        foreach ($this->expenseAllocations($from, $to) as $row) {
            $cursor = $row['from']->copy();
            while ($cursor->lte($row['to'])) {
                $key = $cursor->toDateString();
                $dayIndex = max(0, $cursor->day - 1);
                $daysInMonth = max(1, $row['days_in_month']);
                $base = intdiv($row['total'], $daysInMonth);
                $remainder = $row['total'] % $daysInMonth;
                $dayAmount = $base + ($dayIndex < $remainder ? 1 : 0);
                $totals[$key] = (int) ($totals[$key] ?? 0) + $dayAmount;
                $cursor->addDay();
            }
        }

        return $totals;
    }

    /** @return Collection<int, array{from:CarbonInterface,to:CarbonInterface,total:int,days_in_month:int}> */
    private function expenseAllocations(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $monthStart = $from->copy()->startOfMonth();
        $monthEnd = $to->copy()->endOfMonth();

        return Expense::query()
            ->where(function (Builder $query) use ($monthStart): void {
                $query->where('year', '>', $monthStart->year)
                    ->orWhere(fn (Builder $q) => $q->where('year', $monthStart->year)->where('month', '>=', $monthStart->month));
            })
            ->where(function (Builder $query) use ($monthEnd): void {
                $query->where('year', '<', $monthEnd->year)
                    ->orWhere(fn (Builder $q) => $q->where('year', $monthEnd->year)->where('month', '<=', $monthEnd->month));
            })
            ->get()
            ->map(function (Expense $expense) use ($from, $to): array {
                $month = now()->setDate($expense->year, $expense->month, 1)->startOfDay();
                $monthLastDay = $month->copy()->endOfMonth()->startOfDay();
                $overlapFrom = $from->greaterThan($month) ? $from->copy()->startOfDay() : $month->copy();
                $overlapTo = $to->lessThan($monthLastDay) ? $to->copy()->startOfDay() : $monthLastDay;
                $total = max(0, (int) ($expense->total ?: round($expense->unit_price * (float) $expense->quantity)));

                return [
                    'from' => $overlapFrom,
                    'to' => $overlapTo,
                    'total' => $total,
                    'days_in_month' => $month->daysInMonth,
                ];
            })
            ->filter(fn (array $row): bool => $row['to']->gte($row['from']));
    }

    private function costPriceSql(): string
    {
        // Snapshot giá vốn trên order_items giữ nguyên lịch sử; products chỉ fallback cho dữ liệu cũ.
        return 'COALESCE(NULLIF(order_items.cost_price, 0), products.cost_price, 0)';
    }

    private function inventoryValue(): int
    {
        return (int) WarehouseInventory::query()
            ->leftJoin('products', 'products.id', '=', 'warehouse_inventories.product_id')
            ->selectRaw('SUM(CASE WHEN warehouse_inventories.stock_quantity > 0 THEN warehouse_inventories.stock_quantity ELSE 0 END * COALESCE(products.cost_price, 0)) as aggregate_value')
            ->value('aggregate_value');
    }

    /** @param list<array<string, mixed>> $landingRows
     * @return list<array{level:string,title:string,description:string}>
     */
    private function alerts(
        int $netProfit,
        int $outstandingCod,
        array $landingRows,
        int $lowStock,
        string $budgetBasis,
        int $payrollCost,
        int $estimatedPayrollPlans,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $alerts = [];
        if ($netProfit < 0) {
            $alerts[] = ['level' => 'danger', 'title' => 'Lợi nhuận ròng âm', 'description' => 'Tổng chi phí trong kỳ đang lớn hơn doanh thu đã ghi nhận.'];
        }
        if ($outstandingCod > 0) {
            $alerts[] = ['level' => 'warning', 'title' => 'Còn COD chưa thu', 'description' => 'Đối soát các đơn đã giao nhưng số tiền hãng vận chuyển chưa quyết toán đủ.'];
        }
        if (collect($landingRows)->contains(fn (array $row) => (int) $row['actual'] > (int) $row['planned'])) {
            $alerts[] = ['level' => 'danger', 'title' => 'Nguồn quảng cáo vượt ngân sách', 'description' => 'Có kết nối landing có thực chi cao hơn ngân sách kế hoạch trong kỳ.'];
        }
        if (in_array($budgetBasis, ['planned', 'mixed'], true)) {
            $alerts[] = ['level' => 'info', 'title' => 'Đang dùng ngân sách kế hoạch', 'description' => $budgetBasis === 'mixed' ? 'Một phần ngày/nguồn chưa có thực chi; hệ thống đang dùng kế hoạch cho đúng phần còn thiếu.' : 'Chưa có dữ liệu thực chi theo ngày trong kỳ; dashboard đang tự phân bổ ngân sách đã khai báo.'];
        }
        if ($payrollCost > 0 && $estimatedPayrollPlans > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Chi phí nhân sự có phần dự kiến',
                'description' => "Có {$estimatedPayrollPlans} kế hoạch KPI chưa khóa và chưa nhập ngày công; hệ thống đang tạm tính đủ lương cơ bản tháng.",
            ];
        }
        if ($payrollCost > 0 && $this->potentialPayrollExpenseCount($from, $to) > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Kiểm tra nguy cơ nhập trùng lương',
                'description' => 'Chi phí nhân sự đã được tính tự động từ KPI tháng nhưng danh sách chi phí vận hành có khoản mang nội dung lương/thưởng/hoa hồng.',
            ];
        }
        if ($lowStock > 0) {
            $alerts[] = ['level' => 'warning', 'title' => 'Tồn kho thấp', 'description' => "Có {$lowStock} dòng tồn kho dưới ngưỡng 10 sản phẩm."];
        }

        return $alerts;
    }


    private function potentialPayrollExpenseCount(CarbonInterface $from, CarbonInterface $to): int
    {
        $monthStart = $from->copy()->startOfMonth();
        $monthEnd = $to->copy()->endOfMonth();
        $keywords = ['lương', 'luong', 'thưởng', 'thuong', 'hoa hồng', 'hoa hong', 'salary', 'payroll', 'commission'];

        return Expense::query()
            ->with(['group:id,name', 'category:id,name'])
            ->where(function (Builder $query) use ($monthStart): void {
                $query->where('year', '>', $monthStart->year)
                    ->orWhere(fn (Builder $q) => $q->where('year', $monthStart->year)->where('month', '>=', $monthStart->month));
            })
            ->where(function (Builder $query) use ($monthEnd): void {
                $query->where('year', '<', $monthEnd->year)
                    ->orWhere(fn (Builder $q) => $q->where('year', $monthEnd->year)->where('month', '<=', $monthEnd->month));
            })
            ->get()
            ->filter(function (Expense $expense) use ($keywords): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $expense->name,
                    $expense->note,
                    $expense->group?->name,
                    $expense->category?->name,
                ])));

                return collect($keywords)->contains(fn (string $keyword): bool => str_contains($haystack, $keyword));
            })
            ->count();
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }
}
