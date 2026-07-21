<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\TeamType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Services\OrgStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private readonly OrgStructureService $orgStructure,
        private readonly TeamRepository $teams,
        private readonly UserRepository $users,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'type' => (string) $request->query('type', ''),
            'leader_id' => (string) $request->query('leader_id', ''),
            'search' => trim((string) $request->query('search', '')),
        ];

        $query = Team::query()
            ->with(['leader:id,name,email', 'parent:id,name', 'users:id,name,email,team_id'])
            ->withCount('users');

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }
        if ($filters['leader_id'] !== '') {
            $query->where('leader_user_id', $filters['leader_id']);
        }
        if ($filters['search'] !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        $teams = $query->orderBy('type')->orderBy('name')->paginate(20)->withQueryString()->through(fn (Team $team): array => [
            'id' => $team->id,
            'type' => $team->type?->value,
            'type_label' => $team->type?->label() ?? '',
            'code' => 'TEAM'.str_pad((string) $team->id, 3, '0', STR_PAD_LEFT),
            'name' => $team->name,
            'leader_name' => $team->leader?->name,
            'leader_email' => $team->leader?->email,
            'members_count' => (int) $team->users_count,
            'members' => $team->users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'account' => explode('@', $user->email, 2)[0],
            ])->values(),
            'parent_id' => $team->parent_id,
            'parent_name' => $team->parent?->name,
            'leader_user_id' => $team->leader_user_id,
            'permissions' => is_array($team->permissions) ? $team->permissions : [],
            'updated_at' => $team->updated_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
            'filters' => $filters,
            'types' => $this->typeOptions(),
            'leaders' => $this->leaderOptions(),
            'parents' => $this->parentOptions(),
            'permissionAreas' => $this->permissionAreas(),
            'activeMenuCode' => '1.2.2',
        ]);
    }

    public function create(Request $request): Response
    {
        $parentId = $request->integer('parent_id') ?: null;

        return Inertia::render('Admin/Teams/Form', [
            'team' => $parentId ? ['parent_id' => $parentId] : null,
            'types' => $this->typeOptions(),
            'parents' => $this->parentOptions(),
            'leaders' => $this->leaderOptions(),
            'permissionAreas' => $this->permissionAreas(),
            'activeMenuCode' => '1.2.2',
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['permissions'] = $request->has('permissions')
            ? $this->sanitizePermissions($request)
            : $this->defaultPermissionsForType((string) ($data['type'] ?? ''));

        Team::query()->create($data);

        return redirect()->route('admin.teams.index')->with('success', __('messages.team_created'));
    }

    public function edit(Team $team): Response
    {
        return Inertia::render('Admin/Teams/Form', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'type' => $team->type->value,
                'parent_id' => $team->parent_id,
                'leader_user_id' => $team->leader_user_id,
                'permissions' => is_array($team->permissions) ? $team->permissions : [],
            ],
            'types' => $this->typeOptions(),
            'parents' => $this->parentOptions(excludeId: $team->id),
            'leaders' => $this->leaderOptions(),
            'permissionAreas' => $this->permissionAreas(),
            'activeMenuCode' => '1.2.2',
        ]);
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        if ($request->validated('parent_id') === $team->id) {
            return back()->with('error', __('messages.team_self_parent'));
        }

        if ($this->isDescendant($team, (int) $request->input('parent_id'))) {
            return back()->with('error', __('messages.team_child_parent'));
        }

        $data = $request->validated();
        if ($request->has('permissions')) {
            $data['permissions'] = $this->sanitizePermissions($request);
        } else {
            unset($data['permissions']);
        }

        $team->update($data);

        return redirect()->route('admin.teams.index')->with('success', __('messages.team_updated'));
    }

    public function destroy(Team $team): RedirectResponse
    {
        if ($team->children()->exists()) {
            return back()->with('error', __('messages.team_has_children'));
        }

        if ($team->users()->exists()) {
            return back()->with('error', __('messages.team_has_members'));
        }

        $team->delete();

        return back()->with('success', __('messages.team_deleted'));
    }

    private function isDescendant(Team $team, ?int $parentId): bool
    {
        if (! $parentId) {
            return false;
        }

        $cursorId = $parentId;

        while ($cursorId) {
            if ($cursorId === $team->id) {
                return true;
            }
            $cursorId = $this->teams->parentIdOf($cursorId);
        }

        return false;
    }

    /** @return list<array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return collect(TeamType::cases())
            ->map(fn (TeamType $t) => ['value' => $t->value, 'label' => $t->label()])
            ->values()
            ->all();
    }

    /** @return list<array{id: int, name: string, depth: int}> */
    private function parentOptions(?int $excludeId = null): array
    {
        return $this->teams->indentedOptions($excludeId);
    }

    /** @return list<array{id: int, name: string}> */
    private function leaderOptions(): array
    {
        return $this->users->nameOptions();
    }


    /**
     * Quyền mặc định cấp theo loại đội nhóm để nhóm mới không bị rời rạc khỏi business.
     * Thành viên vẫn có quyền hiệu lực theo user role; quyền team dùng cho các màn cấu hình/report scope.
     *
     * @return array<string, string>
     */
    private function defaultPermissionsForType(string $type): array
    {
        $full = PermissionLevel::Full->value;
        $view = PermissionLevel::View->value;

        return match ($type) {
            TeamType::Sale->value => [
                PermissionArea::Telesale->value => $full,
                PermissionArea::Customers->value => $full,
                PermissionArea::Orders->value => $full,
                PermissionArea::Reports->value => $view,
            ],
            TeamType::Marketing->value => [
                PermissionArea::Marketing->value => $full,
                PermissionArea::Leads->value => $full,
                PermissionArea::Customers->value => $view,
                PermissionArea::Reports->value => $view,
            ],
            TeamType::Warehouse->value => [
                PermissionArea::Warehouse->value => $full,
                PermissionArea::Shipping->value => $full,
                PermissionArea::Orders->value => $view,
                PermissionArea::Reports->value => $view,
            ],
            TeamType::Accounting->value => [
                PermissionArea::Accounting->value => $full,
                PermissionArea::Orders->value => $view,
                PermissionArea::Shipping->value => $view,
                PermissionArea::Reports->value => $view,
            ],
            TeamType::Allocator->value => [
                PermissionArea::Leads->value => $full,
                PermissionArea::Customers->value => $view,
                PermissionArea::Reports->value => $view,
            ],
            default => [],
        };
    }

    /** @return list<string> */
    private function permissionAreas(): array
    {
        return array_map(fn (PermissionArea $a) => $a->value, PermissionArea::cases());
    }

    /**
     * Chỉ giữ mức view/full; bỏ none và giá trị lạ.
     *
     * @return array<string, string>
     */
    private function sanitizePermissions(Request $request): array
    {
        $input = (array) $request->input('permissions', []);
        $clean = [];

        foreach (PermissionArea::cases() as $area) {
            $level = PermissionLevel::fromNullable($input[$area->value] ?? null);
            if ($level !== PermissionLevel::None) {
                $clean[$area->value] = $level->value;
            }
        }

        return $clean;
    }
}
