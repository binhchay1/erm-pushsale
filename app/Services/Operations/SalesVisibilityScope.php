<?php

namespace App\Services\Operations;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source for Sale list/mutate visibility by org hierarchy (tenant-scoped).
 */
class SalesVisibilityScope
{
    public function __construct(private readonly ReportScopeResolver $resolver) {}

    /**
     * null = unrestricted within tenant (Head / Admin).
     * list = whitelist of sale user ids.
     *
     * @return list<int>|null
     */
    public function allowedSaleIds(User $user): ?array
    {
        return $this->resolver->allowedSaleIds($user);
    }

    public function canOperateOrder(User $actor, Order $order): bool
    {
        if (! $actor->isSales()) {
            return true;
        }

        $allowed = $this->allowedSaleIds($actor);
        if ($allowed === null) {
            return true;
        }

        return in_array((int) $order->sale_user_id, $allowed, true);
    }

    /** @param  Builder<Order>  $query */
    public function applyToOrders(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Admin) {
            return $query;
        }

        if (! $user->isSales()) {
            return $query;
        }

        $allowed = $this->allowedSaleIds($user);
        if ($allowed === null) {
            return $query;
        }

        return $query->whereIn('sale_user_id', $allowed);
    }

    public function isElevatedSales(User $user): bool
    {
        if (! $user->isSales()) {
            return false;
        }

        return $user->is_team_leader
            || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true);
    }
}
