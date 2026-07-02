<?php

namespace App\Repositories;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class UserRepository
{
    /**
     * Danh sách {id, name} cho select chọn người (quản lý, leader…).
     *
     * @return list<array{id: int, name: string}>
     */
    public function nameOptions(?int $excludeId = null): array
    {
        return User::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * @param  list<UserRole>  $roles
     * @return list<array{id: int, name: string}>
     */
    public function nameOptionsByRoles(array $roles): array
    {
        return User::query()
            ->whereIn('role', $roles)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /** @return Collection<int, User> */
    public function byRole(UserRole $role, array $columns = ['id', 'name', 'email']): Collection
    {
        return User::query()->where('role', $role)->orderBy('name')->get($columns);
    }

    /**
     * Trưởng kho / leader bộ phận kho — người có quyền ký duyệt nhập xuất.
     *
     * @return list<array{id: int, name: string}>
     */
    public function warehouseApprovers(): array
    {
        return User::query()
            ->where('role', UserRole::Warehouse)
            ->where(function ($q) {
                $q->where('is_team_leader', true)->orWhere('org_level', OrgLevel::Head);
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    public function adminCount(): int
    {
        return User::query()->where('role', UserRole::Admin)->count();
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /** Danh sách nhân viên kèm team + quản lý + người tạo cho màn quản trị. */
    public function allWithTeamAndManager(): Collection
    {
        return User::query()
            ->with(['team:id,name', 'manager:id,name', 'creator:id,name'])
            ->orderBy('name')
            ->get();
    }

    /** Toàn bộ nhân viên kèm team (loại, leader) cho sơ đồ nhân sự. */
    public function allWithTeamForOrgChart(): Collection
    {
        return User::query()
            ->with(['team:id,name,type,leader_user_id'])
            ->orderBy('name')
            ->get();
    }
}
