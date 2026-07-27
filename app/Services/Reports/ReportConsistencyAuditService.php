<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderRevenue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportConsistencyAuditService
{
    public function __construct(
        private readonly ReportQueryService $queries,
        private readonly ReportMetricService $metrics,
    ) {}

    /**
     * Kiểm tra báo cáo theo nguyên tắc: mọi dashboard/report phải xuất phát từ
     * cùng source-of-truth Order/LeadIngestion + OrderRevenue (có kỳ ngày),
     * không lấy số seed hoặc cache lệch.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?User $actor = null, ?ReportFilterData $filter = null): array
    {
        $actor ??= User::query()->where('role', UserRole::Admin)->first();
        if (! $actor) {
            return [
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'status' => 'warning',
                'rows' => [],
                'message' => 'Chưa có user để audit báo cáo.',
            ];
        }

        $filter ??= ReportFilterData::fromRequest(
            Request::create('/report-audit', 'GET', [
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),
                'date_type' => 'data_arrival',
            ]),
            $actor,
        );

        $rows = [];
        foreach ($this->representativeUsers($actor) as $user) {
            $rows = array_merge($rows, $this->auditUser($user, $filter));
        }

        $failed = collect($rows)->where('status', 'fail')->count();
        $warning = collect($rows)->where('status', 'warning')->count();

        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'date_range' => [
                'from' => $filter->dateFrom?->format('d/m/Y'),
                'to' => $filter->dateTo?->format('d/m/Y'),
                'date_type' => $filter->dateType->value,
            ],
            'status' => $failed > 0 ? 'fail' : ($warning > 0 ? 'warning' : 'pass'),
            'failed' => $failed,
            'warning' => $warning,
            'rows' => $rows,
        ];
    }

    /** @return list<User> */
    private function representativeUsers(User $actor): array
    {
        if ($actor->role !== UserRole::Admin) {
            return [$actor];
        }

        $roles = [
            UserRole::Admin,
            UserRole::Sales,
            UserRole::Marketing,
            UserRole::Warehouse,
            UserRole::Accounting,
            UserRole::Allocator,
        ];

        return collect($roles)
            ->map(fn (UserRole $role) => User::query()->where('role', $role)->orderBy('id')->first())
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function auditUser(User $user, ReportFilterData $filter): array
    {
        $orders = $this->queries->orders($user, $filter);
        $leads = $this->queries->leads($user, $filter);
        $rawLeads = $this->queries->rawLeads($user, $filter);
        $kpi = $this->metrics->kpiSummary($user, $filter);

        $truth = [
            'leads' => (clone $leads)->count(),
            'processed_leads' => (clone $leads)->where('status', LeadIngestionStatus::Processed->value)->count(),
            'failed_leads' => (clone $rawLeads)->where('status', LeadIngestionStatus::Failed->value)->count(),
            'duplicate_leads' => (clone $rawLeads)->where('status', LeadIngestionStatus::Duplicate->value)->count(),
            'orders' => (clone $orders)->count(),
            'closed_orders' => (clone $orders)->whereNotNull('closed_at')->count(),
            // Khớp rawKpiSummary / OrderRevenue (có kỳ ngày cho marketing cost).
            'revenue' => OrderRevenue::aggregate(clone $orders, $filter->dateFrom, $filter->dateTo)['net'],
        ];

        $rows = [];
        foreach ($truth as $key => $value) {
            $rows[] = $this->row(
                role: $user->role->value,
                report: 'Dashboard KPI / '.$key,
                expected: (int) $value,
                actual: (int) ($kpi[$key] ?? 0),
                detail: 'So khớp KPI với source Order/LeadIngestion + OrderRevenue theo scope role.'
            );
        }

        $ordersSeries = $this->metrics->orderSeries($user, $filter);
        $rows[] = $this->row(
            role: $user->role->value,
            report: 'Chuỗi đơn theo ngày',
            expected: $truth['orders'],
            actual: collect($ordersSeries)->sum('value'),
            detail: 'Tổng các ngày trên chart phải bằng tổng bản ghi đơn trong kỳ (cùng cột ngày date_type).'
        );

        $leadSeries = $this->metrics->leadSeries($user, $filter);
        $rows[] = $this->row(
            role: $user->role->value,
            report: 'Chuỗi lead theo ngày',
            expected: $truth['leads'],
            actual: collect($leadSeries)->sum('value'),
            detail: 'Tổng các ngày trên chart phải bằng tổng lead countable trong kỳ.'
        );

        $funnel = collect($this->metrics->funnel($user, $filter));
        $closedFunnel = (int) ($funnel->get(3)['value'] ?? 0);
        $deliveredFunnel = (int) ($funnel->get(4)['value'] ?? 0);

        $rows[] = $this->row(
            role: $user->role->value,
            report: 'Funnel - đơn đã chốt',
            expected: $truth['closed_orders'],
            actual: $closedFunnel,
            detail: 'Bước chốt trên funnel phải khớp closed_orders.'
        );

        $rows[] = $this->row(
            role: $user->role->value,
            report: 'Funnel - delivered/paid',
            expected: $truth['closed_orders'],
            actual: $deliveredFunnel,
            detail: 'Đơn delivered/paid không được vượt đơn đã chốt.',
            strict: false,
            ok: $deliveredFunnel <= $truth['closed_orders'],
        );

        $this->appendRoleSpecificAudits($rows, $user, $filter, $orders);

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows @param Builder<Order> $orders */
    private function appendRoleSpecificAudits(array &$rows, User $user, ReportFilterData $filter, Builder $orders): void
    {
        if ($user->role === UserRole::Warehouse) {
            $buckets = $this->metrics->warehouseBuckets($user, $filter);
            $rows[] = $this->row($user->role->value, 'Kho - chờ tạo vận đơn', (clone $orders)->where('delivery_status', 'waiting_waybill')->count(), $buckets['waiting_waybill'] ?? 0, 'Bucket kho phải khớp từng trạng thái giao hàng.');
            $rows[] = $this->row($user->role->value, 'Kho - đang giao', (clone $orders)->where('delivery_status', 'delivering')->count(), $buckets['delivering'] ?? 0, 'Bucket kho phải khớp từng trạng thái giao hàng.');
        }

        if ($user->role === UserRole::Accounting) {
            $buckets = $this->metrics->accountingBuckets($user, $filter);
            $rows[] = $this->row($user->role->value, 'Kế toán - COD chờ đối soát', (clone $orders)->where('delivery_status', 'delivered')->count(), $buckets['pending_cod'] ?? 0, 'Bucket kế toán phải khớp trạng thái delivered.');
            $rows[] = $this->row($user->role->value, 'Kế toán - đã thanh toán', (clone $orders)->where('delivery_status', 'paid')->count(), $buckets['paid'] ?? 0, 'Bucket kế toán phải khớp trạng thái paid.');
        }

        $eligibleRevenue = OrderRevenue::aggregate(
            (clone $orders)->whereIn('delivery_status', DeliveryStatus::revenueEligible()),
            $filter->dateFrom,
            $filter->dateTo,
        )['net'];
        $totalRevenue = OrderRevenue::aggregate(clone $orders, $filter->dateFrom, $filter->dateTo)['net'];
        if ($eligibleRevenue > $totalRevenue) {
            $rows[] = $this->row($user->role->value, 'Doanh thu đủ điều kiện', $totalRevenue, $eligibleRevenue, 'Doanh thu delivered/paid không được vượt tổng net revenue.', strict: false, ok: false);
        }
    }

    private function row(string $role, string $report, int|float $expected, int|float $actual, string $detail, bool $strict = true, ?bool $ok = null): array
    {
        $ok ??= $strict ? ((int) $expected === (int) $actual) : true;
        $diff = (int) $actual - (int) $expected;

        return [
            'role' => $role,
            'report' => $report,
            'expected' => (int) $expected,
            'actual' => (int) $actual,
            'diff' => $diff,
            'status' => $ok ? 'pass' : 'fail',
            'detail' => $detail,
        ];
    }
}
