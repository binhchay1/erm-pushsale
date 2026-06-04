<?php

namespace App\Services;

use App\Enums\OrgLevel;
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

    /** @return array{team_path: list<array{name: string, type_label: string}>, manager_chain: list<array{name: string, org_level_label: string|null}>, direct_reports: list<array{name: string, org_level_label: string|null}>} */
    public function profileContext(User $user): array
    {
        $user->loadMissing([
            'team.parent.parent',
            'manager.manager',
            'members' => fn ($q) => $q->orderBy('name')->limit(20),
        ]);

        $teamPath = [];
        $team = $user->team;
        while ($team) {
            array_unshift($teamPath, [
                'name' => $team->name,
                'type_label' => $team->type->label(),
            ]);
            $team = $team->parent;
        }

        $managerChain = [];
        $manager = $user->manager;
        while ($manager) {
            $managerChain[] = [
                'name' => $manager->name,
                'org_level_label' => $manager->orgLevelLabel(),
            ];
            $manager = $manager->manager;
        }

        $directReports = $user->members
            ->map(fn (User $m) => [
                'name' => $m->name,
                'org_level_label' => $m->orgLevelLabel(),
            ])
            ->values()
            ->all();

        return [
            'team_path' => $teamPath,
            'manager_chain' => $managerChain,
            'direct_reports' => $directReports,
        ];
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
