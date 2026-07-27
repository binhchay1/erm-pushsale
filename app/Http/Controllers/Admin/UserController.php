<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Models\Pushsale\UserOperationalProfile;
use App\Models\Pushsale\WorkShift;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Support\PermissionCatalog;
use App\Services\OrgStructureService;
use App\Services\Users\UserHierarchyService;
use App\Services\Users\UserOrgRules;
use App\Support\ActivityLogger;
use App\Support\TenantEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TeamRepository $teams,
        private readonly UserHierarchyService $hierarchy,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $visibleIds = $this->users->allWithTeamAndManager()
            ->filter(fn (User $user) => $actor && $this->hierarchy->canView($actor, $user))
            ->pluck('id');

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role' => (string) $request->query('role', ''),
            'leader' => (string) $request->query('leader', ''),
            'receive_data' => (string) $request->query('receive_data', ''),
            'locked' => (string) $request->query('locked', ''),
        ];

        $query = User::query()
            ->whereIn('id', $visibleIds)
            ->with([
                'team:id,name',
                'company:id,slug,name',
                'manager:id,name',
                'creator:id,name',
                'operationalProfile.workShift:id,name',
                'operationalProfile.updatedBy:id,name',
            ]);

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhereHas('operationalProfile', fn ($profile) => $profile->where('employee_code', 'like', "%{$term}%"));
            });
        }

        if ($filters['role'] !== '') {
            $query->where('role', $filters['role']);
        }

        if ($filters['leader'] !== '') {
            $query->where('is_team_leader', $filters['leader'] === '1');
        }

        if ($filters['receive_data'] === '1') {
            $query->where(function ($builder): void {
                $builder->whereDoesntHave('operationalProfile')
                    ->orWhereHas('operationalProfile', fn ($profile) => $profile->where('receive_data', true));
            });
        } elseif ($filters['receive_data'] === '0') {
            $query->whereHas('operationalProfile', fn ($profile) => $profile->where('receive_data', false));
        }

        if ($filters['locked'] === '1') {
            $query->whereHas('operationalProfile', fn ($profile) => $profile->where('is_locked', true));
        } elseif ($filters['locked'] === '0') {
            $query->where(function ($builder): void {
                $builder->whereDoesntHave('operationalProfile')
                    ->orWhereHas('operationalProfile', fn ($profile) => $profile->where('is_locked', false));
            });
        }

        $accountCount = (clone $query)->count();

        $users = $query->latest('id')->paginate(15)->withQueryString()->through(function (User $user) use ($actor): array {
            $profile = $user->operationalProfile;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'team_id' => $user->team_id,
                'manager_user_id' => $user->manager_user_id,
                'work_shift_id' => $profile?->work_shift_id,
                'job_title' => $user->job_title,
                'email_local' => $user->company
                    ? (TenantEmail::localPartFromEmail($user->email, $user->company) ?? explode('@', $user->email, 2)[0])
                    : explode('@', $user->email, 2)[0],
                'role_label' => $user->roleLabel(),
                'employee_code' => $profile?->employee_code ?: 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'base_salary' => (int) ($profile?->base_salary ?? 0),
                'phone' => $user->phone,
                'team_name' => $user->team?->name,
                'manager_name' => $user->manager?->name,
                'is_team_leader' => (bool) $user->is_team_leader,
                'receive_data' => (bool) ($profile?->receive_data ?? true),
                'work_shift' => $profile?->workShift?->name,
                'is_locked' => (bool) ($profile?->is_locked ?? false),
                'updated_by' => $profile?->updatedBy?->name ?: $user->creator?->name,
                'updated_at' => $user->updated_at?->format('d/m/Y H:i'),
                'can_manage' => $actor ? $this->hierarchy->canManage($actor, $user) : false,
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])->values(),
            'workShifts' => $this->workShiftOptions(),
            'teams' => $this->teamOptions(),
            'managers' => $this->managerOptions(),
            'emailIdentity' => $this->emailIdentity(),
            'accountCount' => $accountCount,
            'canCreate' => (bool) $actor?->allows(PermissionArea::Hr, \App\Enums\PermissionLevel::Full),
            'activeMenuCode' => '1.2.1',
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
            'workShifts' => $this->workShiftOptions(),
            'activeMenuCode' => '1.2.1',
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $profileData = $this->pullOperationalData($data);
        $data = $this->normalizeUserData($data);
        $data['password'] = Hash::make($data['password']);
        $data['created_by_user_id'] = auth()->id();
        $data['permissions'] = $this->resolvePermissions($request, $data['role'] ?? null);

        $user = User::query()->create($data);
        $this->syncOperationalProfile($user, $profileData);

        ActivityLogger::log(
            ActivityLogger::USER_CREATED,
            $user,
            ['role' => $user->role->value, 'email' => $user->email],
        );

        return redirect()->route('admin.users.index')->with('success', __('messages.user_created'));
    }


    public function storeBulk(Request $request): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->allows(PermissionArea::Hr, \App\Enums\PermissionLevel::Full)) {
            abort(403);
        }

        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'accounts' => ['required', 'string'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'receive_data' => ['sometimes', 'boolean'],
        ]);

        $assignable = array_map(
            fn (UserRole $role) => $role->value,
            $this->hierarchy->assignableRoles($actor),
        );
        if (! in_array((string) $data['role'], $assignable, true)) {
            return back()->withErrors(['role' => __('messages.user_role_not_allowed')]);
        }

        $company = $actor->company;
        if (! $company) {
            return back()->withErrors(['accounts' => 'Không xác định được đơn vị để tạo tài khoản.']);
        }

        $rawAccounts = preg_split('/\R+/', (string) $data['accounts']) ?: [];
        $accounts = collect($rawAccounts)
            ->map(fn ($value) => TenantEmail::normalizeLocalPart((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($accounts->isEmpty()) {
            return back()->withErrors(['accounts' => 'Chưa có tài khoản hợp lệ.']);
        }

        $created = 0;
        $duplicates = [];
        foreach ($accounts as $local) {
            $email = TenantEmail::build($local, $company);
            if (User::query()->where('email', $email)->exists()) {
                $duplicates[] = $local;
                continue;
            }

            $user = User::query()->create([
                'company_id' => $company->id,
                'name' => str_replace(['.', '_', '-'], ' ', $local),
                'email' => $email,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'created_by_user_id' => $actor->id,
                'permissions' => null,
            ]);

            $this->syncOperationalProfile($user, [
                'employee_code' => 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'base_salary' => 0,
                'receive_data' => (bool) ($data['receive_data'] ?? false),
                'work_shift_id' => null,
                'is_locked' => false,
            ]);
            $created++;
        }

        ActivityLogger::log(
            ActivityLogger::USER_CREATED,
            $actor,
            ['bulk_created' => $created, 'duplicates' => $duplicates],
        );

        $message = "Đã tạo {$created} tài khoản.";
        if ($duplicates !== []) {
            $message .= ' Bỏ qua trùng: '.implode(', ', array_slice($duplicates, 0, 8));
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    public function edit(User $user): Response
    {
        $company = auth()->user()?->company;
        $user->loadMissing('operationalProfile');
        $profile = $user->operationalProfile;

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
                'employee_code' => $profile?->employee_code,
                'base_salary' => (int) ($profile?->base_salary ?? 0),
                'receive_data' => (bool) ($profile?->receive_data ?? true),
                'work_shift_id' => $profile?->work_shift_id,
                'is_locked' => (bool) ($profile?->is_locked ?? false),
                'permissions' => $user->permissionsMap(),
            ],
            'roles' => $this->roleOptions(),
            'teams' => $this->teamOptions(),
            'managers' => $this->managerOptions(excludeId: $user->id, role: $user->role->value, orgLevel: $user->org_level?->value),
            'managerPool' => $this->managerPool(),
            'orgLevels' => OrgStructureService::orgLevelOptions(),
            'emailIdentity' => $this->emailIdentity(),
            'permissionConfig' => $this->permissionConfig(),
            'workShifts' => $this->workShiftOptions(),
            'activeMenuCode' => '1.2.1',
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $profileData = $this->pullOperationalData($data);
        $data = $this->normalizeUserData($data);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['permissions'] = $this->resolvePermissions($request, $data['role'] ?? $user->role->value);

        $user->update($data);
        $this->syncOperationalProfile($user, $profileData);

        ActivityLogger::log(
            ActivityLogger::USER_UPDATED,
            $user->fresh(),
            ['role' => $user->role->value],
        );

        return redirect()->route('admin.users.index')->with('success', __('messages.user_updated'));
    }



    public function quickUpdate(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $this->hierarchy->canManage($actor, $user)) {
            abort(403);
        }

        $company = $actor->company ?: $user->company;
        if (! $company) {
            return back()->withErrors(['email_local' => 'Không xác định được đơn vị để cập nhật tài khoản.']);
        }

        $local = TenantEmail::normalizeLocalPart((string) $request->input('email_local', ''));
        $request->merge([
            'email_local' => $local,
            'email' => TenantEmail::build($local, $company),
            'team_id' => $request->input('team_id') ?: null,
            'manager_user_id' => $request->input('manager_user_id') ?: null,
            'work_shift_id' => $request->input('work_shift_id') ?: null,
            'receive_data' => $request->boolean('receive_data', true),
            'is_locked' => $request->boolean('is_locked', false),
            'is_team_leader' => $request->boolean('is_team_leader', false),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email_local' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/i'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'team_id' => ['nullable', 'exists:teams,id'],
            'manager_user_id' => ['nullable', 'exists:users,id', Rule::notIn([$user->id])],
            'is_team_leader' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
            'employee_code' => ['nullable', 'string', 'max:60', Rule::unique('user_operational_profiles', 'employee_code')->ignore($user->operationalProfile?->id)],
            'base_salary' => ['nullable', 'integer', 'min:0'],
            'receive_data' => ['sometimes', 'boolean'],
            'work_shift_id' => ['nullable', 'exists:work_shifts,id'],
            'is_locked' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $assignable = array_map(
            fn (UserRole $role) => $role->value,
            $this->hierarchy->assignableRoles($actor),
        );
        if (! in_array((string) $data['role'], $assignable, true)) {
            return back()->withErrors(['role' => __('messages.user_role_not_allowed')]);
        }

        if (! TenantEmail::acceptsForCompany((string) $data['email'], $company)) {
            return back()->withErrors(['email_local' => __('messages.tenant.invalid_email_suffix', ['suffix' => TenantEmail::suffixFor($company)])]);
        }

        $profileData = [
            'employee_code' => $data['employee_code'] ?? null,
            'base_salary' => $data['base_salary'] ?? 0,
            'receive_data' => (bool) ($data['receive_data'] ?? true),
            'work_shift_id' => $data['work_shift_id'] ?? null,
            'is_locked' => (bool) ($data['is_locked'] ?? false),
        ];

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'team_id' => $data['team_id'] ?? null,
            'manager_user_id' => $data['manager_user_id'] ?? null,
            'is_team_leader' => (bool) ($data['is_team_leader'] ?? false),
            'phone' => $data['phone'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $this->syncOperationalProfile($user, $profileData);

        ActivityLogger::log(
            ActivityLogger::USER_UPDATED,
            $user->fresh(),
            ['quick_update' => true, 'role' => $user->role->value],
        );

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }

    public function bulkUpdateReceiveData(Request $request): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->allows(PermissionArea::Hr, \App\Enums\PermissionLevel::Full)) {
            abort(403);
        }

        $data = $request->validate([
            'accounts' => ['required', 'string'],
            'receive_data' => ['required', 'boolean'],
        ]);

        $identifiers = collect(preg_split('/\R+/', (string) $data['accounts']) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($identifiers->isEmpty()) {
            return back()->withErrors(['accounts' => 'Chưa có tài khoản hợp lệ.']);
        }

        $updated = 0;
        $skipped = [];
        foreach ($identifiers as $identifier) {
            $user = $this->resolveUserIdentifier($actor, $identifier);
            if (! $user) {
                $skipped[] = $identifier;
                continue;
            }

            if (! $this->hierarchy->canManage($actor, $user)) {
                $skipped[] = $identifier;
                continue;
            }

            $profile = $user->operationalProfile()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'company_id' => $user->company_id,
                    'employee_code' => 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'base_salary' => 0,
                    'receive_data' => true,
                    'is_locked' => false,
                    'updated_by_user_id' => $actor->id,
                ],
            );

            $profile->fill(['receive_data' => (bool) $data['receive_data']]);
            $profile->updated_by_user_id = $actor->id;
            $profile->save();
            $updated++;
        }

        if ($updated === 0) {
            return back()->withErrors(['accounts' => 'Không cập nhật được tài khoản nào.']);
        }

        $message = $data['receive_data']
            ? "Đã bật nhận dữ liệu cho {$updated} tài khoản."
            : "Đã tắt nhận dữ liệu cho {$updated} tài khoản.";

        if ($skipped !== []) {
            $message .= ' Bỏ qua: '.implode(', ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '…' : '');
        }

        ActivityLogger::log(
            ActivityLogger::USER_UPDATED,
            $actor,
            ['bulk_receive_data' => ['count' => $updated, 'receive_data' => (bool) $data['receive_data']]],
        );

        return back()->with('success', $message);
    }

    public function updateOperationalStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $this->hierarchy->canManage($actor, $user)) {
            abort(403);
        }

        $data = $request->validate([
            'receive_data' => ['sometimes', 'boolean'],
            'is_locked' => ['sometimes', 'boolean'],
        ]);

        if ($data === []) {
            return back()->with('error', 'Không có trạng thái nào được cập nhật.');
        }

        $profile = $user->operationalProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $user->company_id,
                'employee_code' => 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'base_salary' => 0,
                'receive_data' => true,
                'is_locked' => false,
                'updated_by_user_id' => $actor->id,
            ],
        );

        $profile->fill($data);
        $profile->updated_by_user_id = $actor->id;
        $profile->save();

        ActivityLogger::log(
            ActivityLogger::USER_UPDATED,
            $user,
            ['operational_status' => $data],
        );

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $this->hierarchy->canManage($actor, $user)) {
            abort(403);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        ActivityLogger::log(
            ActivityLogger::USER_UPDATED,
            $user,
            ['password_reset_by_admin' => true],
        );

        return back()->with('success', 'Đã thay đổi mật khẩu tài khoản.');
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

        if (! $request->has('permissions') && $role) {
            $roleEnum = UserRole::tryFrom($role);
            if ($roleEnum instanceof UserRole) {
                return $this->hierarchy->sanitizePermissions($actor, PermissionCatalog::defaultsForRole($roleEnum));
            }
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function pullOperationalData(array &$data): array
    {
        $keys = ['employee_code', 'base_salary', 'receive_data', 'work_shift_id', 'is_locked'];
        $profile = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $profile[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return $profile;
    }

    /** @param array<string, mixed> $data */
    private function syncOperationalProfile(User $user, array $data): void
    {
        UserOperationalProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $user->company_id,
                'employee_code' => ($data['employee_code'] ?? null) ?: 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'base_salary' => (int) ($data['base_salary'] ?? 0),
                'receive_data' => (bool) ($data['receive_data'] ?? true),
                'work_shift_id' => $data['work_shift_id'] ?: null,
                'is_locked' => (bool) ($data['is_locked'] ?? false),
                'updated_by_user_id' => auth()->id(),
            ],
        );
    }

    private function resolveUserIdentifier(User $actor, string $identifier): ?User
    {
        $query = User::query()->with('company');

        if (ctype_digit($identifier)) {
            $user = $query->find((int) $identifier);
        } else {
            $local = TenantEmail::normalizeLocalPart($identifier);
            if ($local === '') {
                return null;
            }

            $company = $actor->company;
            $email = $company ? TenantEmail::build($local, $company) : null;
            $user = $email
                ? $query->where('email', $email)->first()
                : $query->where('email', 'like', $local.'@%')->first();
        }

        if (! $user || ! $this->hierarchy->canView($actor, $user)) {
            return null;
        }

        return $user;
    }

    /** @return list<array{id:int,name:string}> */
    private function workShiftOptions(): array
    {
        return WorkShift::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (WorkShift $shift) => ['id' => $shift->id, 'name' => $shift->name])
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
