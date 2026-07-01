<?php

namespace App\Services\Users;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Quy tắc phân cấp nhân sự: vai trò ↔ cấp bậc ↔ quản lý trực tiếp.
 */
final class UserOrgRules
{
    /** Vai trò có cây quản lý nội bộ (trưởng → giám sát → nhân viên). */
    private const HIERARCHY_ROLES = [
        UserRole::Sales,
        UserRole::Marketing,
        UserRole::Warehouse,
    ];

    public static function usesHierarchy(?string $role): bool
    {
        $enum = UserRole::tryFrom((string) $role);

        return $enum && in_array($enum, self::HIERARCHY_ROLES, true);
    }

    /** @return list<int> */
    public static function managerCandidateIds(string $role, ?string $orgLevel, ?int $excludeId = null): array
    {
        if (! self::usesHierarchy($role)) {
            return User::query()
                ->where('role', UserRole::Admin)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->pluck('id')
                ->all();
        }

        $level = OrgLevel::tryFrom((string) $orgLevel);

        if ($level === OrgLevel::Head) {
            return User::query()
                ->where('role', UserRole::Admin)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->pluck('id')
                ->all();
        }

        return User::query()
            ->where(function ($q) use ($role) {
                $q->where('role', UserRole::Admin)
                    ->orWhere(function ($inner) use ($role) {
                        $inner->where('role', $role)
                            ->where(function ($lvl) {
                                $lvl->where('org_level', OrgLevel::Head->value)
                                    ->orWhere('org_level', OrgLevel::Supervisor->value)
                                    ->orWhere('is_team_leader', true);
                            });
                    });
            })
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    public static function validate(Validator $validator): void
    {
        $data = $validator->getData();
        $role = (string) ($data['role'] ?? '');
        $orgLevel = $data['org_level'] ?? null;
        $managerId = $data['manager_user_id'] ?? null;
        $userId = $validator->getValue('id') ?? request()->route('user')?->id;

        if ($role === UserRole::Admin->value) {
            if ($managerId) {
                $validator->errors()->add('manager_user_id', __('messages.user_org.admin_no_manager'));
            }
            if ($orgLevel) {
                $validator->errors()->add('org_level', __('messages.user_org.admin_no_org_level'));
            }

            return;
        }

        if (! self::usesHierarchy($role)) {
            return;
        }

        $level = OrgLevel::tryFrom((string) $orgLevel);

        if ($level === OrgLevel::Head) {
            if ($managerId) {
                $manager = User::query()->find($managerId);
                if (! $manager || $manager->role !== UserRole::Admin) {
                    $validator->errors()->add('manager_user_id', __('messages.user_org.head_manager_admin_only'));
                }
            }

            return;
        }

        if (! $managerId) {
            $validator->errors()->add('manager_user_id', __('messages.user_org.manager_required'));

            return;
        }

        $manager = User::query()->find($managerId);
        if (! $manager) {
            return;
        }

        if ($manager->id === (int) $userId) {
            $validator->errors()->add('manager_user_id', __('messages.user_org.manager_not_self'));

            return;
        }

        $allowed = self::managerCandidateIds($role, (string) $orgLevel, $userId ? (int) $userId : null);
        if (! in_array((int) $managerId, $allowed, true)) {
            $validator->errors()->add('manager_user_id', __('messages.user_org.manager_invalid'));
        }
    }
}
