<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Support\MarketingPacketMetrics;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Support\MarketingMetrics;
use Illuminate\Support\Collection;

class MarketingTeamTreeService
{
    /**
     * @param  Collection<int, Order>  $orders
     * @return array{roots: list<array<string, mixed>>, maxRevenue: int}
     */
    public function build(ReportFilterData $filter, Collection $orders): array
    {
        $sources = MarketingSource::query()
            ->when($filter->marketerId, fn ($q) => $q->where('marketer_user_id', $filter->marketerId))
            ->get();

        $leadCountsByMarketer = MarketingPacketMetrics::effectiveCountsByMarketer($filter, $orders);

        $marketers = User::query()
            ->where('role', UserRole::Marketing)
            ->when($filter->marketerId, fn ($q) => $q->whereKey($filter->marketerId))
            ->with('team.leader')
            ->orderBy('name')
            ->get();

        if ($marketers->isEmpty()) {
            return ['roots' => [], 'maxRevenue' => 0];
        }

        $teams = Team::query()
            ->where('type', TeamType::Marketing)
            ->orWhereIn('id', $marketers->pluck('team_id')->filter())
            ->with('leader:id,name')
            ->orderBy('name')
            ->get()
            ->unique('id');

        $teamGroups = $marketers->groupBy(fn (User $u) => $u->team_id ?? 0);
        $teamNodes = [];

        foreach ($teamGroups as $teamId => $members) {
            $team = $teams->firstWhere('id', (int) $teamId);
            $memberNodes = $members
                ->map(fn (User $user) => $this->marketerNode($user, $orders, $sources, $leadCountsByMarketer))
                ->values()
                ->all();

            $teamMetrics = $this->aggregateMetrics($memberNodes);
            $teamNodes[] = [
                'id' => 'team-'.($team?->id ?? 'unassigned'),
                'name' => $team?->name ?? __('reports.tree.unassigned_dept'),
                'leaderName' => $team?->leader?->name,
                'memberCount' => count($memberNodes),
                'roleLabel' => __('reports.tree.team_marketing'),
                'type' => 'team',
                'conversionRate' => $teamMetrics['conversionRate'],
                'revenue' => $teamMetrics['revenue'],
                'productQuantity' => $teamMetrics['productQuantity'],
                'closedOrders' => $teamMetrics['closedOrders'],
                'contacts' => $teamMetrics['contacts'],
                'children' => $memberNodes,
            ];
        }

        $director = $this->resolveDirector($teams, $marketers);
        $rootMetrics = $this->aggregateMetrics($teamNodes);

        $roots = [[
            'id' => 'director-'.($director?->id ?? 'head'),
            'name' => $director?->name ?? __('reports.tree.marketing_director'),
            'roleLabel' => $director?->orgLevelLabel() ?? __('reports.tree.leader'),
            'type' => 'director',
            'conversionRate' => $rootMetrics['conversionRate'],
            'revenue' => $rootMetrics['revenue'],
            'productQuantity' => $rootMetrics['productQuantity'],
            'closedOrders' => $rootMetrics['closedOrders'],
            'contacts' => $rootMetrics['contacts'],
            'children' => $teamNodes,
        ]];

        $maxRevenue = $this->maxRevenueInTree($roots);

        return [
            'roots' => $this->applyHighlights($roots, $maxRevenue),
            'maxRevenue' => $maxRevenue,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, MarketingSource>  $sources
     * @return array<string, mixed>
     */
    private function marketerNode(User $user, Collection $orders, Collection $sources, Collection $leadCountsByMarketer): array
    {
        $metrics = $this->metricsForMarketer($user->id, $orders, $sources, $leadCountsByMarketer);

        return [
            'id' => 'marketer-'.$user->id,
            'name' => $user->name,
            'roleLabel' => $user->orgLevelLabel() ?? 'Nhân viên MKT',
            'type' => 'marketer',
            'teamName' => $user->team?->name,
            'conversionRate' => $metrics['conversionRate'],
            'revenue' => $metrics['revenue'],
            'adSpend' => $metrics['adSpend'],
            'roas' => $metrics['roas'],
            'netContribution' => $metrics['netContribution'],
            'productQuantity' => $metrics['productQuantity'],
            'closedOrders' => $metrics['closedOrders'],
            'contacts' => $metrics['contacts'],
            'children' => [],
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, MarketingSource>  $sources
     * @return array{contacts: int, closedOrders: int, revenue: int, adSpend: int, roas: float, netContribution: int, productQuantity: int, conversionRate: float}
     */
    private function metricsForMarketer(int $marketerId, Collection $orders, Collection $sources, Collection $leadCountsByMarketer): array
    {
        $marketerOrders = $orders->where('marketer_user_id', $marketerId);
        $marketerSources = $sources->where('marketer_user_id', $marketerId);
        $contacts = (int) ($leadCountsByMarketer->get($marketerId) ?? 0);
        $closed = $marketerOrders->count();
        $revenueEligible = $marketerOrders->whereIn('delivery_status', DeliveryStatus::revenueEligible());
        $kpi = MarketingMetrics::summarize(
            $revenueEligible,
            $marketerSources->filter(fn (MarketingSource $s) => $s->parent_id === null),
        );
        $revenue = $kpi['attributed_revenue'];
        $productQty = (int) $marketerOrders->sum(fn (Order $o) => $o->items->sum('quantity'));

        return [
            'contacts' => $contacts,
            'closedOrders' => $closed,
            'revenue' => $revenue,
            'adSpend' => $kpi['ad_spend'],
            'roas' => $kpi['roas'],
            'netContribution' => $kpi['net_contribution'],
            'productQuantity' => $productQty,
            'conversionRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{contacts: int, closedOrders: int, revenue: int, productQuantity: int, conversionRate: float}
     */
    private function aggregateMetrics(array $nodes): array
    {
        $contacts = array_sum(array_column($nodes, 'contacts'));
        $closed = array_sum(array_column($nodes, 'closedOrders'));
        $revenue = array_sum(array_column($nodes, 'revenue'));
        $productQty = array_sum(array_column($nodes, 'productQuantity'));

        return [
            'contacts' => $contacts,
            'closedOrders' => $closed,
            'revenue' => $revenue,
            'productQuantity' => $productQty,
            'conversionRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0,
        ];
    }

    /** @param  Collection<int, Team>  $teams
     * @param  Collection<int, User>  $marketers
     */
    private function resolveDirector(Collection $teams, Collection $marketers): ?User
    {
        $leaderId = $teams->first()?->leader_user_id;

        if ($leaderId) {
            $leader = User::query()->find($leaderId);
            if ($leader) {
                return $leader;
            }
        }

        return $marketers->first(fn (User $u) => $u->is_team_leader || $u->org_level === OrgLevel::Head)
            ?? $marketers->first();
    }

    /** @param  list<array<string, mixed>>  $nodes */
    private function maxRevenueInTree(array $nodes): int
    {
        $max = 0;

        foreach ($nodes as $node) {
            $max = max($max, (int) ($node['revenue'] ?? 0));
            if (! empty($node['children'])) {
                $max = max($max, $this->maxRevenueInTree($node['children']));
            }
        }

        return $max;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function applyHighlights(array $nodes, int $maxRevenue): array
    {
        $threshold = $maxRevenue > 0 ? $maxRevenue * 0.75 : 0;

        return array_map(function (array $node) use ($threshold, $maxRevenue) {
            $node['isHighPerformer'] = $maxRevenue > 0 && (int) $node['revenue'] >= $threshold;
            if (! empty($node['children'])) {
                $node['children'] = $this->applyHighlights($node['children'], $maxRevenue);
            }

            return $node;
        }, $nodes);
    }
}
