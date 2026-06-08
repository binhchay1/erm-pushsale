<?php

namespace App\Services;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class OrgStructureService
{
    /** @return list<array<string, mixed>> */
    public function teamTree(?int $parentId = null): array
    {
        $teams = Team::query()
            ->with(['leader:id,name'])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return $this->buildTeamNodes($teams, $parentId);
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array<string, mixed>>
     */
    private function buildTeamNodes(Collection $teams, ?int $parentId): array
    {
        return $teams
            ->where('parent_id', $parentId)
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'type' => $team->type->value,
                'type_label' => $team->type->label(),
                'parent_id' => $team->parent_id,
                'leader_user_id' => $team->leader_user_id,
                'leader_name' => $team->leader?->name,
                'users_count' => (int) $team->users_count,
                'children' => $this->buildTeamNodes($teams, $team->id),
            ])
            ->values()
            ->all();
    }

    /** @return array{scope: string, scope_label: string, roots: list<array<string, mixed>>} */
    public function chartForViewer(User $viewer): array
    {
        $users = User::query()
            ->with(['team:id,name'])
            ->orderBy('name')
            ->get();

        $visibleIds = $this->visibleUserIds($viewer, $users);
        $scope = $this->resolveChartScope($viewer);

        $visibleUsers = $users->whereIn('id', $visibleIds)->values();
        $performanceMetrics = $this->performanceMetricsByUser($visibleUsers);
        $roots = $this->buildUserChartRoots($visibleUsers, $viewer->id, $performanceMetrics);

        return [
            'scope' => $scope,
            'scope_label' => $this->chartScopeLabel($scope),
            'roots' => $roots,
        ];
    }

    /** @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function visibleUserIds(User $viewer, Collection $users): array
    {
        if ($viewer->role === UserRole::Admin) {
            return $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->isOrgManager($viewer)) {
            $director = $this->departmentDirector($viewer, $users);

            return $this->collectDescendantIds($director->id, $users);
        }

        return $this->staffVisibleIds($viewer, $users);
    }

    private function resolveChartScope(User $viewer): string
    {
        if ($viewer->role === UserRole::Admin) {
            return 'admin';
        }

        if ($this->isOrgManager($viewer)) {
            return 'manager';
        }

        return 'staff';
    }

    private function chartScopeLabel(string $scope): string
    {
        return match ($scope) {
            'admin' => 'Toàn công ty',
            'manager' => 'Bộ phận của bạn',
            'staff' => 'Nhóm làm việc trực tiếp',
        };
    }

    public function isOrgManager(User $user): bool
    {
        if ($user->is_team_leader) {
            return true;
        }

        return in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true);
    }

    /** @param  Collection<int, User>  $users */
    private function departmentDirector(User $viewer, Collection $users): User
    {
        $byId = $users->keyBy('id');
        $current = $byId->get($viewer->id) ?? $viewer;
        $director = $current;

        while ($current) {
            if ($this->isDepartmentHead($current)) {
                $director = $current;
                break;
            }

            $current = $current->manager_user_id ? $byId->get($current->manager_user_id) : null;
        }

        if (! $this->isDepartmentHead($director) && $viewer->team_id) {
            $teamLeaderId = Team::query()
                ->whereKey($viewer->team_id)
                ->value('leader_user_id');

            if ($teamLeaderId && $byId->has($teamLeaderId)) {
                return $byId->get($teamLeaderId);
            }
        }

        return $director;
    }

    private function isDepartmentHead(User $user): bool
    {
        if ($user->org_level === OrgLevel::Head) {
            return true;
        }

        return $user->is_team_leader && $user->org_level !== OrgLevel::Staff;
    }

    /** @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function staffVisibleIds(User $viewer, Collection $users): array
    {
        if (! $viewer->manager_user_id) {
            $peerIds = $users
                ->whereNull('manager_user_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return array_values(array_unique(array_merge([$viewer->id], $peerIds)));
        }

        $managerSubtree = $this->collectDescendantIds($viewer->manager_user_id, $users);
        $peerIds = $users
            ->where('manager_user_id', $viewer->manager_user_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($managerSubtree, $peerIds)));
    }

    /** @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function collectDescendantIds(int $rootId, Collection $users): array
    {
        $ids = [$rootId];
        $children = $users->where('manager_user_id', $rootId);

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->collectDescendantIds($child->id, $users));
        }

        return $ids;
    }

    /**
     * @param  Collection<int, User>  $visibleUsers
     * @param  array<int, array{conversionRate: float, revenue: int}>  $performanceMetrics
     * @return list<array<string, mixed>>
     */
    private function buildUserChartRoots(Collection $visibleUsers, int $viewerId, array $performanceMetrics): array
    {
        $visibleIds = $visibleUsers->pluck('id')->flip();

        $roots = $visibleUsers->filter(function (User $user) use ($visibleIds) {
            if (! $user->manager_user_id) {
                return true;
            }

            return ! $visibleIds->has($user->manager_user_id);
        });

        return $roots
            ->sortBy('name')
            ->map(fn (User $user) => $this->userChartNode($user, $visibleUsers, $viewerId, $performanceMetrics))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, User>  $visibleUsers
     * @param  array<int, array{conversionRate: float, revenue: int}>  $performanceMetrics
     * @return array<string, mixed>
     */
    private function userChartNode(
        User $user,
        Collection $visibleUsers,
        int $viewerId,
        array $performanceMetrics,
    ): array {
        $children = $visibleUsers
            ->where('manager_user_id', $user->id)
            ->sortBy('name')
            ->map(fn (User $child) => $this->userChartNode($child, $visibleUsers, $viewerId, $performanceMetrics))
            ->values()
            ->all();

        $metrics = $performanceMetrics[$user->id] ?? null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'job_title' => $user->job_title,
            'role' => $user->role->value,
            'role_label' => $user->roleLabel(),
            'team_name' => $user->team?->name,
            'org_level_label' => $user->orgLevelLabel(),
            'avatar_url' => $user->avatarUrl(),
            'initials' => $user->initials(),
            'is_self' => $user->id === $viewerId,
            'show_metrics' => $metrics !== null,
            'conversion_rate' => $metrics['conversionRate'] ?? null,
            'revenue' => $metrics['revenue'] ?? null,
            'children' => $children,
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{conversionRate: float, revenue: int}>
     */
    private function performanceMetricsByUser(Collection $users): array
    {
        $performanceUserIds = $users
            ->filter(fn (User $user) => in_array($user->role, [UserRole::Sales, UserRole::Marketing], true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($performanceUserIds === []) {
            return [];
        }

        $since = now()->subDays(30)->startOfDay();
        $orders = Order::query()
            ->where('created_at', '>=', $since)
            ->where(function ($query) use ($performanceUserIds) {
                $query->whereIn('sale_user_id', $performanceUserIds)
                    ->orWhereIn('marketer_user_id', $performanceUserIds);
            })
            ->get(['id', 'sale_user_id', 'marketer_user_id', 'closed_at', 'total', 'subtotal', 'discount', 'contact_count']);

        $metrics = [];

        foreach ($performanceUserIds as $userId) {
            $user = $users->firstWhere('id', $userId);
            if (! $user) {
                continue;
            }

            $userOrders = $orders->filter(function (Order $order) use ($user) {
                return $user->role === UserRole::Sales
                    ? (int) $order->sale_user_id === $user->id
                    : (int) $order->marketer_user_id === $user->id;
            });

            $contacts = max($userOrders->count(), 1);
            $closed = $userOrders->whereNotNull('closed_at')->count();
            $revenue = (int) $userOrders
                ->whereNotNull('closed_at')
                ->sum(fn (Order $order) => $order->effectiveRevenue());

            $metrics[$userId] = [
                'conversionRate' => round($closed / $contacts * 100, 1),
                'revenue' => $revenue,
            ];
        }

        return $metrics;
    }

    /** @return list<array{value: string, label: string}> */
    public static function orgLevelOptions(): array
    {
        return collect(OrgLevel::cases())
            ->map(fn (OrgLevel $level) => ['value' => $level->value, 'label' => $level->label()])
            ->values()
            ->all();
    }
}
