<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeamType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Models\Team;
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

    public function index(): Response
    {
        return Inertia::render('Admin/Teams/Index', [
            'tree' => $this->orgStructure->teamTree(),
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
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        Team::query()->create($request->validated());

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
            ],
            'types' => $this->typeOptions(),
            'parents' => $this->parentOptions(excludeId: $team->id),
            'leaders' => $this->leaderOptions(),
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

        $team->update($request->validated());

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
}
