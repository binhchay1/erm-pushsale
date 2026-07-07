<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\ClosingStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AllocatorReportService
{
    public const REPORT_ALLOCATION = 'allocation';

    public const REPORT_LOAD = 'load';

    /** Báo cáo chỉ trưởng bộ phận chia số được xem. */
    public const LEADER_ONLY = [self::REPORT_LOAD];

    /** @return array<string, mixed> */
    public function build(string $report, ReportFilterData $filter): array
    {
        [$from, $to] = $this->range($filter);

        return match ($report) {
            self::REPORT_LOAD => $this->loadReport($from, $to),
            default => $this->allocationReport($from, $to),
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(ReportFilterData $filter): array
    {
        $from = $filter->dateFrom ? $filter->dateFrom->copy()->startOfDay() : now()->subDays(6)->startOfDay();
        $to = $filter->dateTo ? $filter->dateTo->copy()->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    /** @return array<string, mixed> */
    private function allocationReport(Carbon $from, Carbon $to): array
    {
        // Giữ duplicate/failed (báo cáo phân bổ cần tách cột), nhưng loại dòng audit upsell
        // (:upsell) vì đó không phải lead thật đổ về.
        $leads = LeadIngestion::query()
            ->whereBetween('created_at', [$from, $to])
            ->where(fn (Builder $q) => $q->whereNull('external_id')->orWhere('external_id', 'not like', '%:upsell'))
            ->get(['status', 'created_at']);

        $byDay = $leads->groupBy(fn (LeadIngestion $l) => $l->created_at->toDateString());

        $rows = collect();
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $rows->push($this->allocationRow($key, $byDay->get($key, collect())));
        }

        $totals = $this->allocationRow('', $leads, true);

        return [
            'rows' => $rows->sortByDesc('date')->values()->all(),
            'totals' => $totals,
        ];
    }

    /**
     * @param  Collection<int, LeadIngestion>  $leads
     * @return array<string, mixed>
     */
    private function allocationRow(string $date, Collection $leads, bool $isTotal = false): array
    {
        $total = $leads->count();
        $assigned = $leads->where('status', LeadIngestionStatus::Processed->value)->count();
        $pending = $leads->where('status', LeadIngestionStatus::Pending->value)->count();
        $duplicate = $leads->where('status', LeadIngestionStatus::Duplicate->value)->count();
        $failed = $leads->where('status', LeadIngestionStatus::Failed->value)->count();

        // Tỷ lệ chia = lead đã chia / lead chia được (loại trùng & lỗi).
        $allocatable = max(0, $total - $duplicate - $failed);
        $rate = $allocatable > 0 ? round($assigned / $allocatable * 100, 1) : 0.0;

        return [
            'date' => $date,
            'is_total' => $isTotal,
            'total' => $total,
            'assigned' => $assigned,
            'pending' => $pending,
            'duplicate' => $duplicate,
            'failed' => $failed,
            'allocation_rate' => $rate,
        ];
    }

    /** @return array<string, mixed> */
    private function loadReport(Carbon $from, Carbon $to): array
    {
        $sales = User::query()
            ->where('role', UserRole::Sales)
            ->orderBy('name')
            ->get(['id', 'name']);

        $orders = Order::query()
            ->whereNotNull('sale_user_id')
            ->whereBetween('assigned_at', [$from, $to])
            ->get(['sale_user_id', 'closing_status', 'closed_at', 'total', 'subtotal', 'discount']);

        $byUser = $orders->groupBy('sale_user_id');

        $rows = $sales->map(function (User $sale) use ($byUser) {
            $orders = $byUser->get($sale->id, collect());
            $received = $orders->count();
            $closed = $orders->filter(fn (Order $o) => $this->isClosed($o));
            $closedCount = $closed->count();
            $revenue = $closed->sum(fn (Order $o) => $o->netRevenue());

            return [
                'sale_id' => (int) $sale->id,
                'sale_name' => $sale->name,
                'received' => $received,
                'closed' => $closedCount,
                'conversion' => $received > 0 ? round($closedCount / $received * 100, 1) : 0.0,
                'revenue' => (int) $revenue,
            ];
        });

        $totalReceived = $rows->sum('received');
        $totalClosed = $rows->sum('closed');

        return [
            'rows' => $rows->sortByDesc('received')->values()->all(),
            'totals' => [
                'received' => $totalReceived,
                'closed' => $totalClosed,
                'conversion' => $totalReceived > 0 ? round($totalClosed / $totalReceived * 100, 1) : 0.0,
                'revenue' => (int) $rows->sum('revenue'),
            ],
        ];
    }

    private function isClosed(Order $order): bool
    {
        return $order->closing_status === ClosingStatus::Closed->value
            || ($order->closed_at !== null && $order->closing_status !== ClosingStatus::Cancelled->value);
    }
}
