<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing(['team:id,name,type', 'manager:id,name']);

        return Inertia::render('Profile/Index', [
            'profile' => $this->profilePayload($user),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return back()->with('success', __('messages.password_changed'));
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $user->update(['avatar_path' => $path]);

        return back()->with('success', __('messages.avatar_updated'));
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('success', __('messages.avatar_removed'));
    }

    /** @return array<string, mixed> */
    private function profilePayload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'role' => $user->role->value,
            'role_label' => $user->roleLabel(),
            'team_name' => $user->team?->name,
            'manager_name' => $user->manager?->name,
            'org_level' => $user->org_level?->value,
            'org_level_label' => $user->orgLevelLabel(),
            'is_team_leader' => (bool) $user->is_team_leader,
            'avatar_url' => $user->avatarUrl(),
            'initials' => $user->initials(),
        ];
    }
}
