<?php

namespace App\Services;

use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Collection;

class OrgStructureService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

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

    /**
     * Sơ đồ nhân sự dạng khối dọc: Ban giám đốc → từng bộ phận → các team → thành viên.
     * Tránh dạng cây trải ngang khó nhìn khi team đông người.
     *
     * @return array{scope: string, scope_label: string, admins: list<array<string, mixed>>, departments: list<array<string, mixed>>}
     */
    public function chartForViewer(User $viewer): array
    {
        $users = $this->users->allWithTeamForOrgChart();

        $visibleIds = $this->visibleUserIds($viewer, $users);
        $scope = $this->resolveChartScope($viewer);

        $visibleUsers = $users->whereIn('id', $visibleIds)->values();
        $performanceMetrics = $this->performanceMetricsByUser($visibleUsers);

        $admins = $visibleUsers
            ->filter(fn (User $u) => $u->role === UserRole::Admin)
            ->map(fn (User $u) => $this->person($u, $viewer->id, $performanceMetrics))
            ->values()
            ->all();

        return [
            'scope' => $scope,
            'scope_label' => $this->chartScopeLabel($scope),
            'admins' => $admins,
            'departments' => $this->buildDepartments($visibleUsers, $viewer->id, $performanceMetrics),
        ];
    }

    /**
     * @param  Collection<int, User>  $visibleUsers
     * @param  array<int, array{conversionRate: float, revenue: int}>  $metrics
     * @return list<array<string, mixed>>
     */
    private function buildDepartments(Collection $visibleUsers, int $viewerId, array $metrics): array
    {
        $nonAdmins = $visibleUsers->filter(fn (User $u) => $u->role !== UserRole::Admin);

        $byDept = $nonAdmins->groupBy(function (User $u) {
            $type = $u->team?->type ?? $this->teamTypeForRole($u->role);

            return $type?->value ?? 'other';
        });

        $departments = [];

        foreach ($byDept as $typeValue => $members) {
            $type = TeamType::tryFrom((string) $typeValue);
            $head = $members->first(fn (User $u) => $u->org_level === OrgLevel::Head);
            $pool = $members->reject(fn (User $u) => $u->id === $head?->id);

            $teams = [];

            foreach ($pool->filter(fn (User $u) => $u->team_id)->groupBy('team_id') as $teamId => $teamMembers) {
                $team = $teamMembers->first()->team;
                $leader = $teamMembers->first(fn (User $u) => $u->id === (int) ($team?->leader_user_id ?? 0))
                    ?? $teamMembers->first(fn (User $u) => $u->is_team_leader);
                $rest = $teamMembers
                    ->reject(fn (User $u) => $u->id === $leader?->id)
                    ->sortBy('name')
                    ->values();

                $teams[] = [
                    'id' => (int) $teamId,
                    'name' => $team?->name ?? __('org.unnamed_team'),
                    'member_count' => $teamMembers->count(),
                    'leader' => $leader ? $this->person($leader, $viewerId, $metrics) : null,
                    'members' => $rest->map(fn (User $u) => $this->person($u, $viewerId, $metrics))->all(),
                ];
            }

            usort($teams, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

            $unassigned = $pool
                ->filter(fn (User $u) => ! $u->team_id)
                ->sortBy('name')
                ->map(fn (User $u) => $this->person($u, $viewerId, $metrics))
                ->values()
                ->all();

            $departments[] = [
                'key' => (string) $typeValue,
                'name' => $type?->label() ?? __('org.other_department'),
                'member_count' => $members->count(),
                'head' => $head ? $this->person($head, $viewerId, $metrics) : null,
                'teams' => $teams,
                'unassigned' => $unassigned,
            ];
        }

        $order = ['sale', 'marketing', 'warehouse', 'allocator', 'accounting', 'other'];
        usort($departments, function (array $a, array $b) use ($order) {
            return array_search($a['key'], $order, true) <=> array_search($b['key'], $order, true);
        });

        return $departments;
    }

    /** @param  array<int, array{conversionRate: float, revenue: int}>  $metrics
     * @return array<string, mixed>
     */
    private function person(User $user, int $viewerId, array $metrics): array
    {
        $m = $metrics[$user->id] ?? null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'job_title' => $user->job_title,
            'role' => $user->role->value,
            'role_label' => $user->roleLabel(),
            'org_level_label' => $user->orgLevelLabel(),
            'avatar_url' => $user->avatarUrl(),
            'initials' => $user->initials(),
            'is_self' => $user->id === $viewerId,
            'show_metrics' => $m !== null,
            'conversion_rate' => $m['conversionRate'] ?? null,
            'revenue' => $m['revenue'] ?? null,
        ];
    }

    /**
     * Phạm vi nhìn thấy theo business:
     * - Admin: toàn công ty (admin cũng là người duy nhất tạo/phân chia team — route /admin/teams).
     * - Trưởng bộ phận (org_level = head): toàn bộ ngành của mình (mọi team cùng loại + nhân sự cùng ngành).
     * - Leader & nhân viên: chỉ team của mình.
     *
     * @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function visibleUserIds(User $viewer, Collection $users): array
    {
        if ($viewer->role === UserRole::Admin) {
            return $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($viewer->org_level === OrgLevel::Head) {
            return $this->departmentMemberIds($viewer, $users);
        }

        return $this->teamMemberIds($viewer, $users);
    }

    private function resolveChartScope(User $viewer): string
    {
        if ($viewer->role === UserRole::Admin) {
            return 'admin';
        }

        if ($viewer->org_level === OrgLevel::Head) {
            return 'department';
        }

        return 'team';
    }

    private function chartScopeLabel(string $scope): string
    {
        return match ($scope) {
            'admin' => __('org.scope.company'),
            'department' => __('org.scope.department'),
            'team' => __('org.scope.team'),
            default => __('org.scope.team'),
        };
    }

    /**
     * Trưởng bộ phận thấy toàn ngành: mọi user thuộc team cùng loại,
     * hoặc cùng ngành (theo role) nếu chưa được gán team.
     *
     * @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function departmentMemberIds(User $viewer, Collection $users): array
    {
        $viewerRecord = $users->firstWhere('id', $viewer->id) ?? $viewer;
        $type = $viewerRecord->team?->type ?? $this->teamTypeForRole($viewer->role);

        return $users
            ->filter(function (User $user) use ($type, $viewer) {
                if ($user->id === $viewer->id) {
                    return true;
                }

                if ($user->role === UserRole::Admin || $type === null) {
                    return false;
                }

                if ($user->team) {
                    return $user->team->type === $type;
                }

                return $this->teamTypeForRole($user->role) === $type;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Leader & nhân viên chỉ thấy team của mình (gồm cả leader của team).
     *
     * @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function teamMemberIds(User $viewer, Collection $users): array
    {
        if (! $viewer->team_id) {
            return [$viewer->id];
        }

        $ids = $users
            ->where('team_id', $viewer->team_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $viewerRecord = $users->firstWhere('id', $viewer->id);
        $leaderId = (int) ($viewerRecord?->team?->leader_user_id ?? 0);

        if ($leaderId) {
            $ids[] = $leaderId;
        }

        $ids[] = $viewer->id;

        return array_values(array_unique($ids));
    }

    private function teamTypeForRole(UserRole $role): ?TeamType
    {
        return match ($role) {
            UserRole::Sales => TeamType::Sale,
            UserRole::Marketing => TeamType::Marketing,
            UserRole::Warehouse => TeamType::Warehouse,
            UserRole::Allocator => TeamType::Allocator,
            UserRole::Accounting => TeamType::Accounting,
            default => null,
        };
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
