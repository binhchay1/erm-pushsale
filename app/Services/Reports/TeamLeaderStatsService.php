<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Thống kê trưởng nhóm — gom theo team marketing (team → nhân viên).
 *
 * Phân quyền:
 * - Admin / trưởng bộ phận (org_level = Head): xem toàn bộ các nhóm.
 * - Trưởng nhóm: chỉ xem nhóm của mình.
 * - Nhân viên: chỉ xem số liệu của bản thân.
 *
 * Báo cáo tổng hợp nặng → nên chạy qua snapshot cache (xem ReportSnapshotService).
 */
class TeamLeaderStatsService
{
    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    /** @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>} */
    public function build(User $user, ReportFilterData $filter): array
    {
        $marketerIds = $this->visibleMarketerIds($user, $filter);

        $marketers = User::query()
            ->where('role', UserRole::Marketing)
            ->when($marketerIds !== null, fn ($q) => $q->whereIn('id', $marketerIds))
            ->with(['team:id,name,leader_user_id', 'team.leader:id,name'])
            ->orderBy('name')
            ->get();

        if ($marketers->isEmpty()) {
            return ['rows' => [], 'totals' => []];
        }

        $ids = $marketers->pluck('id')->all();
        $sources = MarketingSource::query()->whereIn('marketer_user_id', $ids)->get();
        $orders = $this->fetchOrders($ids, $filter);
        $leadCountsByMarketer = $this->leadCountsByMarketer($ids, $filter);

        $teamGroups = $marketers->groupBy(fn (User $u) => $u->team_id ?? 0);
        $rows = [];
        $stt = 0;

        foreach ($teamGroups as $members) {
            $stt++;
            $team = $members->first()->team;

            $memberRows = $members
                ->map(fn (User $m) => $this->marketerRow($m, $orders, $sources, $leadCountsByMarketer))
                ->sortByDesc('revenueTotal')
                ->values()
                ->all();

            $teamRow = $this->aggregate($memberRows);
            $teamRow['id'] = 'team-'.($team?->id ?? 'unassigned');
            $teamRow['stt'] = $stt;
            $teamRow['name'] = $team?->name ?? __('reports.tree.unassigned_team');
            $teamRow['leaderName'] = $team?->leader?->name;
            $teamRow['isTeam'] = true;
            $teamRow['children'] = $memberRows;
            $rows[] = $teamRow;
        }

        usort($rows, fn ($a, $b) => $b['revenueTotal'] <=> $a['revenueTotal']);

        return ['rows' => $rows, 'totals' => $this->grandTotals($rows)];
    }

    /** @param  Collection<int, Order>  $orders
     * @param  Collection<int, MarketingSource>  $sources
     * @return array<string, mixed> */
    private function marketerRow(User $user, Collection $orders, Collection $sources, Collection $leadCountsByMarketer): array
    {
        $mineOrders = $orders->where('marketer_user_id', $user->id);
        $mineSources = $sources->where('marketer_user_id', $user->id);

        $budget = (int) $mineSources->sum('budget');
        $periodLeads = (int) ($leadCountsByMarketer->get($user->id) ?? 0);
        $contacts = max($periodLeads, (int) $mineOrders->sum('contact_count'));

        $closedOrders = $mineOrders->filter(fn (Order $o) => (string) $o->closing_status === 'closed');
        $closed = $closedOrders->count();
        $revenueTotal = (int) $closedOrders->sum(fn (Order $o) => $o->netRevenue());
        $revenueNew = (int) $closedOrders->where('is_returning_customer', false)->sum(fn (Order $o) => $o->netRevenue());
        $revenueOld = (int) $closedOrders->where('is_returning_customer', true)->sum(fn (Order $o) => $o->netRevenue());
        $kpiRevenue = (int) $closedOrders
            ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
            ->sum(fn (Order $o) => $o->netRevenue());

        return [
            'id' => 'marketer-'.$user->id,
            'name' => $user->name,
            'isTeam' => false,
            'budget' => $budget,
            'contacts' => $contacts,
            'costPerContact' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closed' => $closed,
            'closeRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0,
            'revenueNew' => $revenueNew,
            'revenueOld' => $revenueOld,
            'revenueTotal' => $revenueTotal,
            'budgetRevenueRatio' => $revenueTotal > 0 ? round($budget / $revenueTotal * 100, 1) : 0,
            'kpiRevenue' => $kpiRevenue,
            'kpiRate' => $revenueTotal > 0 ? round($kpiRevenue / $revenueTotal * 100, 1) : 0,
        ];
    }

    /** @param  list<array<string, mixed>>  $memberRows
     * @return array<string, mixed> */
    private function aggregate(array $memberRows): array
    {
        $budget = array_sum(array_column($memberRows, 'budget'));
        $contacts = array_sum(array_column($memberRows, 'contacts'));
        $closed = array_sum(array_column($memberRows, 'closed'));
        $revenueTotal = array_sum(array_column($memberRows, 'revenueTotal'));
        $revenueNew = array_sum(array_column($memberRows, 'revenueNew'));
        $revenueOld = array_sum(array_column($memberRows, 'revenueOld'));
        $kpiRevenue = array_sum(array_column($memberRows, 'kpiRevenue'));

        return [
            'budget' => $budget,
            'contacts' => $contacts,
            'costPerContact' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
            'closed' => $closed,
            'closeRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0,
            'revenueNew' => $revenueNew,
            'revenueOld' => $revenueOld,
            'revenueTotal' => $revenueTotal,
            'budgetRevenueRatio' => $revenueTotal > 0 ? round($budget / $revenueTotal * 100, 1) : 0,
            'kpiRevenue' => $kpiRevenue,
            'kpiRate' => $revenueTotal > 0 ? round($kpiRevenue / $revenueTotal * 100, 1) : 0,
        ];
    }

    /** @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed> */
    private function grandTotals(array $rows): array
    {
        $totals = $this->aggregate($rows);
        $totals['name'] = __('reports.grand_total');

        return $totals;
    }

    /** @return Collection<int, Order> */
    private function fetchOrders(array $marketerIds, ReportFilterData $filter): Collection
    {
        $column = match ($filter->dateType) {
            DateType::SaleReceived => 'assigned_at',
            DateType::Closing => 'closed_at',
            default => 'data_arrived_at',
        };

        return Order::query()
            ->with('items:id,order_id,quantity')
            ->whereIn('marketer_user_id', $marketerIds)
            ->when(
                $filter->dateFrom && $filter->dateTo,
                fn ($q) => $q->whereBetween($column, [$filter->dateFrom, $filter->dateTo]),
            )
            ->get();
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

        // Trưởng bộ phận marketing xem toàn bộ.
        if ($user->org_level === OrgLevel::Head) {
            return null;
        }

        return $this->scope->allowedMarketerIds($user);
    }

    /** @return \Illuminate\Support\Collection<int, int> marketer_user_id => lead count */
    private function leadCountsByMarketer(array $marketerIds, ReportFilterData $filter): Collection
    {
        $query = LeadIngestion::query()
            ->join('marketing_sources', 'lead_ingestions.marketing_source_id', '=', 'marketing_sources.id')
            ->selectRaw('marketing_sources.marketer_user_id as marketer_id, COUNT(*) as aggregate')
            ->whereIn('marketing_sources.marketer_user_id', $marketerIds)
            ->whereNotNull('lead_ingestions.marketing_source_id')
            ->groupBy('marketing_sources.marketer_user_id');

        if ($filter->dateFrom && $filter->dateTo) {
            $query->whereBetween('lead_ingestions.created_at', [$filter->dateFrom, $filter->dateTo]);
        }

        return $query->pluck('aggregate', 'marketer_id');
    }
}
