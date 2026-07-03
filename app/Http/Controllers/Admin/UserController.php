<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Support\PermissionCatalog;
use App\Services\OrgStructureService;
use App\Services\Users\UserHierarchyService;
use App\Services\Users\UserOrgRules;
use App\Support\ActivityLogger;
use App\Support\TenantEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TeamRepository $teams,
        private readonly UserHierarchyService $hierarchy,
    ) {}

    public function index(): Response
    {
        $actor = auth()->user();

        $users = $this->users->allWithTeamAndManager()
            ->filter(fn (User $u) => $u->id === $actor?->id || ($actor && $this->hierarchy->canManage($actor, $u)))
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
                'creator_name' => $u->creator?->name,
                'can_manage' => $actor ? $this->hierarchy->canManage($actor, $u) : false,
            ])
            ->values();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'canCreate' => (bool) $actor?->allows(PermissionArea::Hr, \App\Enums\PermissionLevel::Full),
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
            'emailIdentity' => $this->emailIdentity(),
            'permissionConfig' => $this->permissionConfig(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->normalizeUserData($request->validated());
        $data['password'] = Hash::make($data['password']);
        $data['created_by_user_id'] = auth()->id();
        $data['permissions'] = $this->resolvePermissions($request, $data['role'] ?? null);

        $user = User::query()->create($data);

        ActivityLogger::log(
            ActivityLogger::USER_CREATED,
            $user,
            ['role' => $user->role->value, 'email' => $user->email],
        );

        return redirect()->route('admin.users.index')->with('success', __('messages.user_created'));
    }

    public function edit(User $user): Response
    {
        $company = auth()->user()?->company;

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_local' => $company
                    ? (TenantEmail::localPartFromEmail($user->email, $company) ?? explode('@', $user->email, 2)[0])
                    : explode('@', $user->email, 2)[0],
                'role' => $user->role->value,
                'team_id' => $user->team_id,
                'manager_user_id' => $user->manager_user_id,
                'is_team_leader' => (bool) $user->is_team_leader,
                'org_level' => $user->org_level?->value,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'permissions' => $user->permissionsMap(),
            ],
            'roles' => $this->roleOptions(),
            'teams' => $this->teamOptions(),
            'managers' => $this->managerOptions(excludeId: $user->id, role: $user->role->value, orgLevel: $user->org_level?->value),
            'managerPool' => $this->managerPool(),
            'orgLevels' => OrgStructureService::orgLevelOptions(),
            'emailIdentity' => $this->emailIdentity(),
            'permissionConfig' => $this->permissionConfig(),
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

        $data['permissions'] = $this->resolvePermissions($request, $data['role'] ?? $user->role->value);

        $user->update($data);

        ActivityLogger::log(
            ActivityLogger::USER_UPDATED,
            $user->fresh(),
            ['role' => $user->role->value],
        );

        return redirect()->route('admin.users.index')->with('success', __('messages.user_updated'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('messages.user_cannot_delete_self'));
        }

        $actor = auth()->user();
        if (! $actor || ! $this->hierarchy->canManage($actor, $user)) {
            abort(403);
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
        $actor = auth()->user();
        $assignable = $actor ? $this->hierarchy->assignableRoles($actor) : UserRole::cases();

        return collect($assignable)
            ->map(fn (UserRole $r) => ['value' => $r->value, 'label' => $r->label()])
            ->values()
            ->all();
    }

    /**
     * Cấu hình phân quyền cho form: danh mục khu vực, mức tối đa actor được cấp,
     * và mức mặc định theo từng vai trò (để prefill khi đổi vai trò).
     *
     * @return array<string, mixed>
     */
    private function permissionConfig(): array
    {
        $actor = auth()->user();

        $defaultsByRole = [];
        foreach (UserRole::cases() as $role) {
            $defaultsByRole[$role->value] = PermissionCatalog::defaultsForRole($role);
        }

        return [
            'areas' => array_map(fn (PermissionArea $a) => $a->value, PermissionArea::cases()),
            'grantable' => $actor ? $this->hierarchy->grantableAreas($actor) : PermissionCatalog::allFull(),
            'defaultsByRole' => $defaultsByRole,
        ];
    }

    /**
     * @return array<string, string>|null Null cho admin (toàn quyền mặc định).
     */
    private function resolvePermissions(UserRequest $request, ?string $role): ?array
    {
        if ($role === UserRole::Admin->value) {
            return null;
        }

        $actor = auth()->user();
        if (! $actor) {
            return null;
        }

        return $this->hierarchy->sanitizePermissions($actor, (array) $request->input('permissions', []));
    }

    /** @return list<array{id: int, name: string, permissions: array<string, string>}> */
    private function teamOptions(): array
    {
        $permById = \App\Models\Team::query()
            ->get(['id', 'permissions'])
            ->mapWithKeys(fn ($t) => [$t->id => is_array($t->permissions) ? $t->permissions : []])
            ->all();

        return array_map(
            fn (array $o) => [
                'id' => $o['id'],
                'name' => $o['name'],
                'permissions' => $permById[$o['id']] ?? [],
            ],
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

    /** @return array<string, mixed> */
    private function emailIdentity(): array
    {
        $company = auth()->user()?->company;

        if (! $company) {
            return [
                'suffix' => '@'.TenantEmail::domain(),
                'host' => TenantEmail::domain(),
                'isInternal' => true,
                'companySlug' => TenantEmail::internalSlug(),
                'companyName' => TenantEmail::internalName(),
                'roleLocalParts' => [],
            ];
        }

        $roleLocalParts = [];
        foreach (UserRole::cases() as $role) {
            $roleLocalParts[$role->value] = TenantEmail::localPartFromEmail(
                TenantEmail::forRole($role, $company),
                $company,
            ) ?? $role->value;
        }

        return [
            'suffix' => TenantEmail::suffixFor($company),
            'host' => TenantEmail::hostFor($company),
            'isInternal' => $company->slug === TenantEmail::internalSlug(),
            'companySlug' => $company->slug,
            'companyName' => $company->name,
            'roleLocalParts' => $roleLocalParts,
        ];
    }
}
