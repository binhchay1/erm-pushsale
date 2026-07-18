<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\DiscountMode;
use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Marketing\MarketingBudgetService;
use App\Support\LeadContactMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Marketing Leader → Thống kê trưởng nhóm.
 *
 * Màn này tái hiện UI/UX báo cáo Pushsale nhưng dùng nguồn dữ liệu ERM:
 * - contacts: lead gốc countable, không cộng thêm packet upsell;
 * - chốt đơn: chỉ tính đơn chốt từ các contact gốc;
 * - doanh số: đơn chốt theo nhân sự marketing, có tách KHM/KHC;
 * - màu cột/progress tính ở frontend theo max của tập kết quả để giống bảng Pushsale.
 */
class TeamLeaderStatsService
{
    public function __construct(
        private readonly ReportScopeResolver $scope,
        private readonly ReportQueryService $queries,
        private readonly MarketingBudgetService $budgets,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user, ReportFilterData $filter): array
    {
        $marketers = $this->visibleMarketers($user, $filter);

        if ($marketers->isEmpty()) {
            return [
                'rows' => [],
                'totals' => $this->emptyTotals(),
                'statusSummary' => $this->emptyStatusSummary(),
            ];
        }

        $marketerIds = $marketers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $orders = $this->queries->orders($user, $filter)
            ->with([
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
                'marketerUser:id,name,email,role,team_id',
                'marketerUser.team:id,name,leader_user_id,type',
                'marketingSource:id,name,marketer_user_id,budget',
            ])
            ->whereIn('marketer_user_id', $marketerIds)
            ->get();

        $statusSummary = $this->statusSummary($orders);
        $leadCountsByMarketer = LeadContactMetrics::effectiveCountsByMarketer($filter, $orders)->only($marketerIds);
        $contactOrderIds = LeadContactMetrics::contactOrderIds($orders);

        $from = ($filter->dateFrom ?? Carbon::now()->startOfMonth())->copy()->startOfDay();
        $to = ($filter->dateTo ?? Carbon::now()->endOfMonth())->copy()->endOfDay();
        $plans = $this->plansForRange($from, $to);
        $sources = MarketingSource::query()
            ->whereIn('marketer_user_id', $marketerIds)
            ->get(['id', 'name', 'marketer_user_id', 'budget']);

        $rows = $marketers->map(function (User $marketer) use ($orders, $sources, $leadCountsByMarketer, $contactOrderIds, $plans, $from, $to, $filter): array {
            return $this->marketerRow($marketer, $orders, $sources, $leadCountsByMarketer, $contactOrderIds, $plans, $from, $to, $filter);
        })
            ->sortByDesc('totalAfterDiscount')
            ->values()
            ->map(fn (array $row, int $index): array => array_merge($row, ['stt' => $index + 1]))
            ->all();

        $perPage = $filter->perPage >= 999999 ? count($rows) : $filter->perPage;
        $rows = array_slice($rows, 0, $perPage ?: 20);

        return [
            'rows' => $rows,
            'totals' => $this->aggregate($rows),
            'statusSummary' => $statusSummary,
        ];
    }

    /** @return Collection<int, User> */
    private function visibleMarketers(User $user, ReportFilterData $filter): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Marketing)
            ->with('team:id,name,leader_user_id,type')
            ->orderBy('name');

        $allowed = $this->visibleMarketerIds($user, $filter);
        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        $query->when($filter->marketingTeamLeaderId, function ($q) use ($filter): void {
            $q->whereHas('team', fn ($team) => $team->where('leader_user_id', $filter->marketingTeamLeaderId));
        });

        $query->when($filter->marketingTeamId, fn ($q) => $q->where('team_id', $filter->marketingTeamId));
        $query->when($filter->marketerId, fn ($q) => $q->where('id', $filter->marketerId));

        return $query->get();
    }

    /** @param Collection<int, Order> $orders */
    private function statusSummary(Collection $orders): array
    {
        $summary = $this->emptyStatusSummary();
        foreach (DeliveryStatus::ceoSummaryMap() as $key => $status) {
            $summary[$key] = $orders->where('delivery_status', $status->value)->count();
        }

        return $summary;
    }

    /** @param Collection<int, Order> $orders
     *  @param Collection<int, MarketingSource> $sources
     *  @param Collection<int, int> $leadCountsByMarketer
     *  @param Collection<int, MonthlyKpiPlan> $plans
     *  @param Collection<int, int> $contactOrderIds
     *  @return array<string, mixed>
     */
    private function marketerRow(
        User $marketer,
        Collection $orders,
        Collection $sources,
        Collection $leadCountsByMarketer,
        Collection $contactOrderIds,
        Collection $plans,
        Carbon $from,
        Carbon $to,
        ReportFilterData $filter,
    ): array {
        $mine = $orders->where('marketer_user_id', $marketer->id)->values();
        $mineSources = $sources->where('marketer_user_id', $marketer->id)->values();
        $sourceIds = $mineSources->pluck('id')->map('intval')->unique()->values();
        $budgetResult = $sourceIds->isNotEmpty()
            ? $this->budgets->effectiveForSourceIds($sourceIds, $from, $to)
            : ['amount' => 0, 'actual' => 0, 'planned' => 0, 'basis' => 'none'];
        $budget = (int) $budgetResult['amount'];
        $contacts = (int) ($leadCountsByMarketer->get($marketer->id) ?? 0);

        $contactClosed = $mine
            ->whereIn('id', $contactOrderIds)
            ->filter(fn (Order $order): bool => $this->isClosed($order));
        $closed = $contactClosed->count();
        $closedOrders = $mine->filter(fn (Order $order): bool => $this->isClosed($order))->values();
        $newOrders = $closedOrders->where('is_returning_customer', false)->values();
        $oldOrders = $closedOrders->where('is_returning_customer', true)->values();
        $items = $closedOrders->flatMap(fn (Order $order) => $order->items);
        $upsellItems = $items->where('item_type', 'upsell');

        $newRevenue = $this->sumRevenue($newOrders, $filter->discountMode);
        $oldRevenue = $this->sumRevenue($oldOrders, $filter->discountMode);
        $totalRevenue = $this->sumRevenue($closedOrders, $filter->discountMode);
        $discount = (int) $closedOrders->sum('discount');
        $totalAfterDiscount = (int) max(0, $totalRevenue);
        $kpiRevenue = (int) collect($plans->get($marketer->id, collect()))->sum('revenue_target');
        $codFee = (int) $closedOrders->sum('cod_fee');
        $codSupport = (int) $closedOrders->sum('cod_support');
        $deposit = (int) $closedOrders->sum('deposit');
        $upsellRevenue = (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal());

        return [
            'id' => 'marketer-'.$marketer->id,
            'marketerId' => (int) $marketer->id,
            'marketerName' => $marketer->name,
            'marketerUsername' => strstr((string) $marketer->email, '@', true) ?: $marketer->email,
            'teamName' => $marketer->team?->name,
            'teamLeaderId' => $marketer->team?->leader_user_id,
            'budget' => $budget,
            'contacts' => $contacts,
            'contactPrice' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closed' => $closed,
            'closeRate' => $this->pct($closed, $contacts),
            'newEstRevenue' => $newRevenue,
            'budgetRevenueRatioNew' => $this->pct($budget, $newRevenue),
            'budgetRevenueRatioNewAfterDiscount' => $this->pct($budget, max(1, $newRevenue - $discount)),
            'oldEstRevenue' => $oldRevenue,
            'totalEstRevenue' => $totalRevenue,
            'budgetRevenueRatioTotal' => $this->pct($budget, $totalRevenue),
            'codFee' => $codFee,
            'codSupport' => $codSupport,
            'discount' => $discount,
            'deposit' => $deposit,
            'totalAfterDiscount' => $totalAfterDiscount,
            'marketingKpi' => $kpiRevenue,
            'achievementRate' => $this->pct($totalAfterDiscount, $kpiRevenue),
            'upsellQty' => (int) $upsellItems->sum('quantity'),
            'upsellRevenue' => $upsellRevenue,
            'upsellRevenueShare' => $this->pct($upsellRevenue, $totalAfterDiscount),
        ];
    }

    /** @param Collection<int, Order> $orders */
    private function sumRevenue(Collection $orders, DiscountMode $mode): int
    {
        return (int) $orders->sum(function (Order $order) use ($mode): int {
            if ($mode === DiscountMode::BeforeDiscount) {
                return (int) max((int) $order->subtotal, (int) $order->total);
            }

            return $order->netRevenue();
        });
    }

    /** @return Collection<int, Collection<int, MonthlyKpiPlan>> */
    private function plansForRange(Carbon $from, Carbon $to): Collection
    {
        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $months->push([$cursor->year, $cursor->month]);
            $cursor->addMonth();
        }

        return MonthlyKpiPlan::query()
            ->where(function ($query) use ($months): void {
                foreach ($months as [$year, $month]) {
                    $query->orWhere(fn ($monthQuery) => $monthQuery->where('year', $year)->where('month', $month));
                }
            })
            ->get()
            ->groupBy('user_id');
    }

    /** @param list<array<string, mixed>> $rows */
    private function aggregate(array $rows): array
    {
        $sum = fn (string $key): int|float => array_sum(array_map(fn ($row) => (float) ($row[$key] ?? 0), $rows));
        $budget = $sum('budget');
        $contacts = $sum('contacts');
        $closed = $sum('closed');
        $newRevenue = $sum('newEstRevenue');
        $oldRevenue = $sum('oldEstRevenue');
        $totalRevenue = $sum('totalEstRevenue');
        $discount = $sum('discount');
        $afterDiscount = $sum('totalAfterDiscount');
        $kpi = $sum('marketingKpi');
        $upsellRevenue = $sum('upsellRevenue');

        return [
            'budget' => (int) $budget,
            'contacts' => (int) $contacts,
            'contactPrice' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closed' => (int) $closed,
            'closeRate' => $this->pct($closed, $contacts),
            'newEstRevenue' => (int) $newRevenue,
            'budgetRevenueRatioNew' => $this->pct($budget, $newRevenue),
            'budgetRevenueRatioNewAfterDiscount' => $this->pct($budget, max(1, $newRevenue - $discount)),
            'oldEstRevenue' => (int) $oldRevenue,
            'totalEstRevenue' => (int) $totalRevenue,
            'budgetRevenueRatioTotal' => $this->pct($budget, $totalRevenue),
            'codFee' => (int) $sum('codFee'),
            'codSupport' => (int) $sum('codSupport'),
            'discount' => (int) $discount,
            'deposit' => (int) $sum('deposit'),
            'totalAfterDiscount' => (int) $afterDiscount,
            'marketingKpi' => (int) $kpi,
            'achievementRate' => $this->pct($afterDiscount, $kpi),
            'upsellQty' => (int) $sum('upsellQty'),
            'upsellRevenue' => (int) $upsellRevenue,
            'upsellRevenueShare' => $this->pct($upsellRevenue, $afterDiscount),
        ];
    }

    private function emptyTotals(): array
    {
        return $this->aggregate([]);
    }

    private function emptyStatusSummary(): array
    {
        return [
            'waitingDelivery' => 0,
            'cancelWaybill' => 0,
            'delivering' => 0,
            'delivered' => 0,
            'paid' => 0,
            'returned' => 0,
        ];
    }

    private function isClosed(Order $order): bool
    {
        return $order->closed_at !== null
            || (string) $order->closing_status === ClosingStatus::Closed->value;
    }

    private function pct(int|float $numerator, int|float $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : 0.0;
    }

    /** null = xem toàn bộ. */
    private function visibleMarketerIds(User $user, ReportFilterData $filter): ?array
    {
        if ($user->role === UserRole::Admin) {
            return $filter->marketerId ? [$filter->marketerId] : null;
        }

        if ($user->role !== UserRole::Marketing) {
            return [$user->id];
        }

        if ($user->org_level === OrgLevel::Head) {
            return null;
        }

        return $this->scope->allowedMarketerIds($user);
    }
}
