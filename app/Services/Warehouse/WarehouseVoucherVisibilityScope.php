<?php

namespace App\Services\Warehouse;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ai xem được phiếu xuất/nhập kho (5.3.1 / 5.3.2).
 *
 * - Admin / chủ DN / kế toán / cấp quản lý kho: xem tất cả trong tenant.
 * - Trưởng nhóm kho: phiếu của mình + thành viên cùng team.
 * - Nhân viên kho (cấp dưới): chỉ phiếu mình tạo.
 */
class WarehouseVoucherVisibilityScope
{
    /**
     * null = unrestricted trong tenant.
     *
     * @return list<int>|null
     */
    public function allowedCreatorIds(User $user): ?array
    {
        if ($user->isAdmin() || $user->isPlatformAdmin() || $user->isOwner()) {
            return null;
        }

        if ($user->role === UserRole::Accounting) {
            return null;
        }

        if ($user->role === UserRole::Warehouse) {
            $elevated = $user->is_team_leader
                || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true);

            if ($elevated && ! $user->is_team_leader) {
                // Head / Supervisor kho: toàn bộ phiếu.
                return null;
            }

            if ($user->is_team_leader && $user->team_id) {
                $ids = User::query()
                    ->where('team_id', $user->team_id)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                return array_values(array_unique([...$ids, (int) $user->id]));
            }

            return [(int) $user->id];
        }

        // Role khác (nếu được mở menu): chỉ phiếu mình.
        return [(int) $user->id];
    }

    public function canView(User $user, WarehouseVoucher $voucher): bool
    {
        $allowed = $this->allowedCreatorIds($user);
        if ($allowed === null) {
            return true;
        }

        return in_array((int) $voucher->created_by_user_id, $allowed, true);
    }

    public function assertCanView(User $user, WarehouseVoucher $voucher): void
    {
        if ($this->canView($user, $voucher)) {
            return;
        }

        throw new HttpException(403, 'Bạn chỉ được xem phiếu kho do chính mình tạo.');
    }

    /** @param  Builder<WarehouseVoucher>  $query */
    public function applyToVouchers(Builder $query, User $user): Builder
    {
        $allowed = $this->allowedCreatorIds($user);
        if ($allowed === null) {
            return $query;
        }

        return $query->whereIn('created_by_user_id', $allowed);
    }
}
