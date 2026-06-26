<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\OperationResult;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class SalesPerformanceReportService
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly ReportScopeResolver $scope,
        private readonly SalesTeamTreeService $teamTree,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter, User $viewer): array
    {
        $orders = $this->queries->orders($viewer, $filter)->get();
        $salesUsers = $this->salesUsers($viewer, $filter);

        $rows = [];
        $totals = $this->emptyMetrics();

        foreach ($salesUsers as $index => $user) {
            $mine = $orders->where('sale_user_id', $user->id);
            $metrics = $this->metricsForOrders($mine);
            $row = array_merge([
                'stt' => $index + 1,
                'saleId' => (string) $user->id,
                'saleName' => $user->name,
                'isTotalRow' => false,
            ], $metrics);
            $rows[] = $row;
            $totals = $this->mergeMetrics($totals, $metrics);
        }

        array_unshift($rows, array_merge([
            'stt' => 0,
            'saleId' => 'total',
            'saleName' => __('reports.total'),
            'isTotalRow' => true,
        ], $this->finalizeMetrics($totals)));

        return [
            'rows' => $rows,
            'columns' => $this->columns(),
            // Cây doanh số theo team — chỉ admin (sale chỉ thấy số của mình)
            'teamTree' => $viewer->isAdmin() ? $this->teamTree->build($orders) : null,
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'saleName', 'label' => __('reports.sales_performance.sale_name')],
            ['key' => 'totalLeads', 'label' => __('reports.sales_performance.total_leads')],
            ['key' => 'actualCalls', 'label' => __('reports.sales_performance.actual_calls')],
            ['key' => 'answerRate', 'label' => __('reports.sales_performance.answer_rate')],
            ['key' => 'closedOrders', 'label' => __('reports.sales_performance.closed_orders')],
            ['key' => 'closeRate', 'label' => __('reports.sales_performance.close_rate')],
            ['key' => 'totalRevenue', 'label' => __('reports.sales_performance.total_revenue')],
        ];
    }

    /** @return Collection<int, User> */
    private function salesUsers(User $viewer, ReportFilterData $filter): Collection
    {
        $query = User::query()->where('role', UserRole::Sales)->orderBy('name');

        if ($filter->saleId) {
            $query->whereKey($filter->saleId);
        } elseif ($viewer->role === UserRole::Sales) {
            $query->whereIn('id', $this->scope->allowedSaleIds($viewer));
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, int|float>
     */
    private function metricsForOrders(Collection $orders): array
    {
        $totalLeads = $orders->count();
        $actualCalls = (int) $orders->sum(fn (Order $o) => max(0, (int) $o->contact_count - 1));
        $answered = $orders->filter(fn (Order $o) => $this->isAnswered($o))->count();
        $closed = $orders->whereNotNull('closed_at')->count();
        $revenue = (int) $orders
            ->whereNotNull('closed_at')
            ->sum(fn (Order $o) => $o->effectiveRevenue());

        return [
            'totalLeads' => $totalLeads,
            'actualCalls' => $actualCalls,
            'answeredCount' => $answered,
            'answerRate' => $totalLeads > 0 ? round($answered / $totalLeads * 100, 1) : 0,
            'closedOrders' => $closed,
            'closeRate' => $totalLeads > 0 ? round($closed / $totalLeads * 100, 1) : 0,
            'totalRevenue' => $revenue,
        ];
    }

    private function isAnswered(Order $order): bool
    {
        if ($order->closed_at) {
            return true;
        }

        $result = OperationResult::tryFromStored($order->operation_result);

        return $result?->indicatesAnswered() ?? false;
    }

    /** @return array<string, int|float> */
    private function emptyMetrics(): array
    {
        return [
            'totalLeads' => 0,
            'actualCalls' => 0,
            'answeredCount' => 0,
            'answerRate' => 0,
            'closedOrders' => 0,
            'closeRate' => 0,
            'totalRevenue' => 0,
        ];
    }

    /**
     * @param  array<string, int|float>  $totals
     * @param  array<string, int|float>  $metrics
     * @return array<string, int|float>
     */
    private function mergeMetrics(array $totals, array $metrics): array
    {
        return [
            'totalLeads' => $totals['totalLeads'] + $metrics['totalLeads'],
            'actualCalls' => $totals['actualCalls'] + $metrics['actualCalls'],
            'answeredCount' => $totals['answeredCount'] + $metrics['answeredCount'],
            'answerRate' => 0,
            'closedOrders' => $totals['closedOrders'] + $metrics['closedOrders'],
            'closeRate' => 0,
            'totalRevenue' => $totals['totalRevenue'] + $metrics['totalRevenue'],
        ];
    }

    /**
     * @param  array<string, int|float>  $totals
     * @return array<string, int|float>
     */
    private function finalizeMetrics(array $totals): array
    {
        $leads = (int) $totals['totalLeads'];

        return array_merge($totals, [
            'answerRate' => $leads > 0 ? round($totals['answeredCount'] / $leads * 100, 1) : 0,
            'closeRate' => $leads > 0 ? round($totals['closedOrders'] / $leads * 100, 1) : 0,
        ]);
    }
}
