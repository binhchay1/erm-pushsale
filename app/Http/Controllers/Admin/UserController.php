<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Services\OrgStructureService;
use App\Services\Users\UserOrgRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TeamRepository $teams,
    ) {}

    public function index(): Response
    {
        $users = $this->users->allWithTeamAndManager()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'role_label' => $u->roleLabel(),
                'team_name' => $u->team?->name,
                'manager_name' => $u->manager?->name,
                'is_team_leader' => (bool) $u->is_team_leader,
                'org_level' => $u->org_level?->value,
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
            'managerPool' => $this->managerPool(),
            'orgLevels' => OrgStructureService::orgLevelOptions(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->normalizeUserData($request->validated());
        $data['password'] = Hash::make($data['password']);

        User::query()->create($data);

        return redirect()->route('admin.users.index')->with('success', __('messages.user_created'));
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
            'managers' => $this->managerOptions(excludeId: $user->id, role: $user->role->value, orgLevel: $user->org_level?->value),
            'managerPool' => $this->managerPool(),
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

        return redirect()->route('admin.users.index')->with('success', __('messages.user_updated'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('messages.user_cannot_delete_self'));
        }

        if ($user->role === UserRole::Admin && $this->users->adminCount() <= 1) {
            return back()->with('error', __('messages.user_cannot_delete_last_admin'));
        }

        $user->delete();

        return back()->with('success', __('messages.user_deleted'));
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
        return array_map(
            fn (array $o) => ['id' => $o['id'], 'name' => $o['name']],
            $this->teams->indentedOptions(),
        );
    }

    /** @return list<array{id: int, name: string}> */
    private function managerOptions(?int $excludeId = null, ?string $role = null, ?string $orgLevel = null): array
    {
        if (! $role) {
            return $this->users->nameOptions($excludeId);
        }

        $ids = UserOrgRules::managerCandidateIds($role, $orgLevel, $excludeId);

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /** @return list<array{id: int, name: string, role: string, org_level: ?string, is_team_leader: bool}> */
    private function managerPool(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'org_level', 'is_team_leader'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role->value,
                'org_level' => $u->org_level?->value,
                'is_team_leader' => (bool) $u->is_team_leader,
            ])
            ->all();
    }
}
