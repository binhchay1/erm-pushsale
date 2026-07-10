<?php

namespace App\Services\Reports;

use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Support\LeadContactMetrics;
use Illuminate\Support\Collection;

/**
 * Cây doanh số Telesale: Trưởng bộ phận → Team (leader) → Nhân viên.
 * Theo dõi tỷ lệ chốt + doanh thu theo từng nhánh team.
 */
class SalesTeamTreeService
{
    /**
     * @param  Collection<int, Order>  $orders
     * @return array{roots: list<array<string, mixed>>, maxRevenue: int}
     */
    public function build(Collection $orders): array
    {
        $salesUsers = User::query()
            ->where('role', UserRole::Sales)
            ->with('team.leader')
            ->orderBy('name')
            ->get();

        if ($salesUsers->isEmpty()) {
            return ['roots' => [], 'maxRevenue' => 0];
        }

        $teams = Team::query()
            ->where('type', TeamType::Sale)
            ->orWhereIn('id', $salesUsers->pluck('team_id')->filter())
            ->with('leader:id,name')
            ->orderBy('name')
            ->get()
            ->unique('id');

        $teamNodes = [];

        foreach ($salesUsers->groupBy(fn (User $u) => $u->team_id ?? 0) as $teamId => $members) {
            $team = $teams->firstWhere('id', (int) $teamId);
            $memberNodes = $members
                ->map(fn (User $user) => $this->saleNode($user, $orders))
                ->values()
                ->all();

            $teamMetrics = $this->aggregateMetrics($memberNodes);
            $teamNodes[] = [
                'id' => 'team-'.($team?->id ?? 'unassigned'),
                'name' => $team?->name ?? __('reports.tree.unassigned_team'),
                'leaderName' => $team?->leader?->name,
                'memberCount' => count($memberNodes),
                'roleLabel' => $team?->leader ? __('reports.tree.leader_prefix', ['name' => $team->leader->name]) : __('reports.tree.team_telesale'),
                'type' => 'team',
                'conversionRate' => $teamMetrics['conversionRate'],
                'revenue' => $teamMetrics['revenue'],
                'closedOrders' => $teamMetrics['closedOrders'],
                'contacts' => $teamMetrics['contacts'],
                'children' => $memberNodes,
            ];
        }

        $director = $this->resolveDirector($teams, $salesUsers);
        $rootMetrics = $this->aggregateMetrics($teamNodes);

        $roots = [[
            'id' => 'director-'.($director?->id ?? 'head'),
            'name' => $director?->name ?? __('reports.tree.sales_director'),
            'roleLabel' => $director?->orgLevelLabel() ?? __('reports.tree.dept_head'),
            'type' => 'director',
            'conversionRate' => $rootMetrics['conversionRate'],
            'revenue' => $rootMetrics['revenue'],
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
     * @return array<string, mixed>
     */
    private function saleNode(User $user, Collection $orders): array
    {
        $mine = $orders->where('sale_user_id', $user->id);
        $contactOrders = $mine->whereIn('id', LeadContactMetrics::contactOrderIds($mine));
        $contacts = $contactOrders->count();
        $closed = $contactOrders->whereNotNull('closed_at')->count();
        $revenue = (int) $mine
            ->whereNotNull('closed_at')
            ->sum(fn (Order $o) => $o->netRevenue());

        return [
            'id' => 'sale-'.$user->id,
            'name' => $user->name,
            'roleLabel' => $user->orgLevelLabel() ?? 'Telesale',
            'type' => 'sale',
            'teamName' => $user->team?->name,
            'conversionRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0.0,
            'revenue' => $revenue,
            'closedOrders' => $closed,
            'contacts' => $contacts,
            'children' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{contacts: int, closedOrders: int, revenue: int, conversionRate: float}
     */
    private function aggregateMetrics(array $nodes): array
    {
        $contacts = array_sum(array_column($nodes, 'contacts'));
        $closed = array_sum(array_column($nodes, 'closedOrders'));
        $revenue = array_sum(array_column($nodes, 'revenue'));

        return [
            'contacts' => $contacts,
            'closedOrders' => $closed,
            'revenue' => $revenue,
            'conversionRate' => $contacts > 0 ? round($closed / $contacts * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, User>  $salesUsers
     */
    private function resolveDirector(Collection $teams, Collection $salesUsers): ?User
    {
        $head = $salesUsers->first(fn (User $u) => $u->org_level === OrgLevel::Head);

        if ($head) {
            return $head;
        }

        $leaderId = $teams->first()?->leader_user_id;

        return $leaderId ? User::query()->find($leaderId) : $salesUsers->first();
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
