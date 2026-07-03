<?php

namespace App\Services\Users;

use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Xác định "nhánh trên / nhánh dưới" để quản lý nhân viên & phân quyền:
 * người ở nhánh trên chỉnh được nhánh dưới, không chỉnh được nhánh trên/ngang.
 */
class UserHierarchyService
{
    /** Thứ hạng càng nhỏ càng cao. */
    public function rank(User $user): int
    {
        if ($user->isSuperAdmin()) {
            return 0;
        }
        if ($user->isAdmin()) {
            return 1;
        }
        if ($user->org_level === OrgLevel::Head) {
            return 2;
        }
        if ($user->org_level === OrgLevel::Supervisor || $user->is_team_leader) {
            return 3;
        }

        return 4; // staff
    }

    /**
     * Actor có được NHÌN THẤY target trong danh sách nhân sự không.
     * Tách khỏi canManage (quyền sửa) để đồng bộ đúng với sơ đồ tổ chức:
     * - Admin & super admin (role Admin): thấy toàn bộ nhân sự công ty.
     * - Trưởng bộ phận (head): toàn ngành (cùng role).
     * - Giám sát / trưởng nhóm: cấp dưới trực tiếp hoặc cùng team.
     * - Nhân viên: chỉ chính mình.
     */
    public function canView(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        // Admin công ty và super admin (đều role Admin) thấy toàn bộ nhân sự.
        if ($actor->isAdmin()) {
            return true;
        }

        // Không lộ super admin cho cấp dưới.
        if ($target->isSuperAdmin()) {
            return false;
        }

        return $this->inScope($actor, $target);
    }

    /** Actor có được phép chỉnh sửa (thông tin + quyền) target không. */
    public function canManage(User $actor, User $target): bool
    {
        // Không ai chỉnh được super admin qua màn nhân sự công ty.
        if ($target->isSuperAdmin()) {
            return false;
        }

        // Admin công ty & super admin (đều là admin nội bộ): full toàn bộ nhân viên.
        if ($actor->isAdmin()) {
            return true;
        }

        // Không tự chỉnh quyền của chính mình (tránh tự nâng quyền).
        if ($actor->id === $target->id) {
            return false;
        }

        // Chỉ chỉnh được người có thứ hạng thấp hơn.
        if ($this->rank($actor) >= $this->rank($target)) {
            return false;
        }

        return $this->inScope($actor, $target);
    }

    /** Target có nằm trong phạm vi quản lý của actor (phòng ban / nhóm / cấp dưới trực tiếp). */
    private function inScope(User $actor, User $target): bool
    {
        // Trưởng bộ phận: quản lý toàn bộ nhân sự cùng phòng ban (cùng vai trò).
        if ($actor->org_level === OrgLevel::Head) {
            return $actor->role === $target->role;
        }

        // Giám sát / trưởng nhóm: quản lý cấp dưới trực tiếp hoặc cùng nhóm.
        if ($target->manager_user_id === $actor->id) {
            return true;
        }

        return $actor->team_id && $actor->team_id === $target->team_id;
    }

    /**
     * Các vai trò actor được phép gán khi tạo/sửa nhân viên.
     *
     * @return list<UserRole>
     */
    public function assignableRoles(User $actor): array
    {
        if ($actor->isAdmin()) {
            return UserRole::cases();
        }

        // Cấp dưới admin chỉ tạo/gán người cùng vai trò của mình (cùng phòng ban).
        return $actor->role instanceof UserRole ? [$actor->role] : [];
    }

    /**
     * Mức quyền tối đa actor có thể cấp cho mỗi khu vực (không vượt quyền của chính actor).
     *
     * @return array<string, string> area => maxLevel
     */
    public function grantableAreas(User $actor): array
    {
        $map = [];
        foreach (PermissionArea::cases() as $area) {
            $max = $actor->isAdmin()
                ? PermissionLevel::Full
                : $actor->permissionLevel($area);
            $map[$area->value] = $max->value;
        }

        return $map;
    }

    /**
     * Chuẩn hoá quyền gửi lên: chỉ giữ mức <= mức actor được cấp; loại giá trị lạ.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function sanitizePermissions(User $actor, array $input): array
    {
        $grantable = $this->grantableAreas($actor);
        $clean = [];

        foreach (PermissionArea::cases() as $area) {
            $requested = PermissionLevel::fromNullable($input[$area->value] ?? null);
            $max = PermissionLevel::fromNullable($grantable[$area->value] ?? null);

            // Không cho cấp vượt quyền của actor.
            $level = $requested->rank() <= $max->rank() ? $requested : $max;

            if ($level !== PermissionLevel::None) {
                $clean[$area->value] = $level->value;
            }
        }

        return $clean;
    }
}
