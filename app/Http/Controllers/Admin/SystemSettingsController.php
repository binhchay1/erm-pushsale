<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public const ROLE_PERMISSION_KEY = 'role_permission_defaults';

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canManagePlatform(), 403);

        $stored = $this->storedRolePermissions();
        $defaults = [];
        foreach (UserRole::cases() as $role) {
            $defaults[$role->value] = array_replace(PermissionCatalog::baseDefaultsForRole($role), $stored[$role->value] ?? []);
        }

        return Inertia::render('System/Settings', [
            'activeMenuCode' => '10.1.4',
            'areas' => array_map(fn (PermissionArea $area): array => [
                'key' => $area->value,
                'label' => __('permissions.area.'.$area->value),
            ], PermissionArea::cases()),
            'roles' => array_map(fn (UserRole $role): array => [
                'key' => $role->value,
                'label' => $role->label(),
            ], UserRole::cases()),
            'rolePermissions' => $defaults,
            'users' => User::query()
                ->orderBy('role')
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'email', 'role', 'permissions'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value ?? (string) $user->role,
                    'effective_permissions' => $user->permissionsMap(),
                    'custom_permissions' => is_array($user->permissions) ? $user->permissions : [],
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManagePlatform(), 403);

        $validated = $request->validate([
            'role_permissions' => ['required', 'array'],
            'role_permissions.*' => ['array'],
        ]);

        $clean = [];
        $allowedAreas = PermissionArea::values();
        $allowedLevels = array_map(fn (PermissionLevel $level): string => $level->value, PermissionLevel::cases());

        foreach (UserRole::cases() as $role) {
            $rolePayload = (array) ($validated['role_permissions'][$role->value] ?? []);
            $clean[$role->value] = [];
            foreach ($allowedAreas as $area) {
                $level = (string) ($rolePayload[$area] ?? PermissionLevel::None->value);
                $clean[$role->value][$area] = in_array($level, $allowedLevels, true) ? $level : PermissionLevel::None->value;
            }
        }

        AppSetting::setPlatform(self::ROLE_PERMISSION_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        ActivityLogger::log('settings.system_role_permissions_updated', properties: [
            'roles' => array_keys($clean),
            'areas' => $allowedAreas,
        ], subjectLabel: 'Cấu hình quyền toàn hệ thống');

        return back()->with('success', 'Đã cập nhật cấu hình quyền toàn hệ thống.');
    }

    /** @return array<string, array<string, string>> */
    private function storedRolePermissions(): array
    {
        $raw = AppSetting::getPlatform(self::ROLE_PERMISSION_KEY, '{}') ?: '{}';
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
