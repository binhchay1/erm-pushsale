<?php

namespace App\Repositories;

use App\Models\Team;
use Illuminate\Support\Collection;

class TeamRepository
{
    /** @return Collection<int, Team> */
    public function allOrdered(array $columns = ['id', 'name', 'parent_id']): Collection
    {
        return Team::query()->orderBy('name')->get($columns);
    }

    /** Danh sách team kèm loại — dùng cho bộ lọc báo cáo. */
    public function optionsWithType(): Collection
    {
        return $this->allOrdered(['id', 'name', 'type']);
    }

    /**
     * Danh sách team thụt cấp theo cây cha–con (cho select).
     *
     * @return list<array{id: int, name: string, depth: int}>
     */
    public function indentedOptions(?int $excludeId = null): array
    {
        $teams = $this->allOrdered();
        $options = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, &$options, $teams, $excludeId): void {
            foreach ($teams->where('parent_id', $parentId) as $team) {
                if ($excludeId && $team->id === $excludeId) {
                    continue;
                }
                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $options[] = ['id' => $team->id, 'name' => $prefix.$team->name, 'depth' => $depth];
                $walk($team->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $options;
    }

    public function parentIdOf(int $teamId): ?int
    {
        $value = Team::query()->whereKey($teamId)->value('parent_id');

        return $value !== null ? (int) $value : null;
    }
}
