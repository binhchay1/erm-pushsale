<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\OrgStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with(['team:id,name', 'manager:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'role_label' => $u->roleLabel(),
                'team_name' => $u->team?->name,
                'manager_name' => $u->manager?->name,
                'is_team_leader' => (bool) $u->is_team_leader,
                'org_level_label' => $u->orgLevelLabel(),
                'job_title' => $u->job_title,
            ])
            ->values();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'teams' => $this->teamOptions(),
            'managers' => $this->managerOptions(),
            'orgLevels' => OrgStructureService::orgLevelOptions(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->normalizeUserData($request->validated());
        $data['password'] = Hash::make($data['password']);

        User::query()->create($data);

        return redirect()->route('admin.users.index')->with('success', 'Đã tạo nhân viên.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'team_id' => $user->team_id,
                'manager_user_id' => $user->manager_user_id,
                'is_team_leader' => (bool) $user->is_team_leader,
                'org_level' => $user->org_level?->value,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
            ],
            'roles' => $this->roleOptions(),
            'teams' => $this->teamOptions(),
            'managers' => $this->managerOptions(excludeId: $user->id),
            'orgLevels' => OrgStructureService::orgLevelOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $this->normalizeUserData($request->validated());

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Đã cập nhật nhân viên.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể xóa tài khoản đang đăng nhập.');
        }

        if ($user->role === UserRole::Admin && User::query()->where('role', UserRole::Admin)->count() <= 1) {
            return back()->with('error', 'Không thể xóa quản trị viên cuối cùng.');
        }

        $user->delete();

        return back()->with('success', 'Đã xóa nhân viên.');
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeUserData(array $data): array
    {
        $data['is_team_leader'] = (bool) ($data['is_team_leader'] ?? false);

        if (($data['org_level'] ?? null) === OrgLevel::Head->value) {
            $data['is_team_leader'] = true;
        }

        return $data;
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        return collect(UserRole::cases())
            ->map(fn (UserRole $r) => ['value' => $r->value, 'label' => $r->label()])
            ->values()
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function teamOptions(): array
    {
        $teams = Team::query()->orderBy('name')->get(['id', 'name', 'parent_id']);
        $options = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, &$options, $teams): void {
            foreach ($teams->where('parent_id', $parentId) as $team) {
                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $options[] = ['id' => $team->id, 'name' => $prefix.$team->name];
                $walk($team->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $options;
    }

    /** @return list<array{id: int, name: string}> */
    private function managerOptions(?int $excludeId = null): array
    {
        return User::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
