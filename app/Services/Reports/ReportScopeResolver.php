<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportScopeResolver
{
    /** @param  Builder<Order>  $query */
    public function applyOrderScope(Builder $query, User $user, ReportFilterData $filter): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Sales => $this->scopeSalesOrders($query, $user),
            UserRole::Marketing => $this->scopeMarketingOrders($query, $user),
            UserRole::Warehouse => $this->scopeWarehouseOrders($query, $filter),
            UserRole::Accounting => $this->scopeAccountingOrders($query),
            UserRole::Allocator => $this->scopeAllocatorOrders($query),
        };
    }

    /** @param  Builder<LeadIngestion>  $query */
    public function applyLeadScope(Builder $query, User $user, ReportFilterData $filter): Builder
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Allocator => $query,
            UserRole::Sales => $query->whereHas('order', fn (Builder $order) => $this->scopeSalesOrders($order, $user)),
            UserRole::Marketing => $query->whereHas('order', fn (Builder $order) => $this->scopeMarketingOrders($order, $user)),
            UserRole::Warehouse => $query->whereHas('order', fn (Builder $order) => $this->scopeWarehouseOrders($order, $filter)),
            UserRole::Accounting => $query->whereHas('order', fn (Builder $order) => $this->scopeAccountingOrders($order)),
        };
    }

    /** @return list<int> */
    public function allowedSaleIds(User $user): array
    {
        if ($user->role === UserRole::Admin) {
            return User::query()->where('role', UserRole::Sales)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->role !== UserRole::Sales) {
            return [];
        }

        if (! $user->is_team_leader) {
            return [$user->id];
        }

        return User::query()
            ->where('role', UserRole::Sales)
            ->where(function (Builder $query) use ($user) {
                $query->where('id', $user->id)
                    ->orWhere('manager_user_id', $user->id)
                    ->when($user->team_id, fn (Builder $q) => $q->orWhere('team_id', $user->team_id));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function allowedMarketerIds(User $user): array
    {
        if ($user->role === UserRole::Admin) {
            return User::query()->where('role', UserRole::Marketing)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->role !== UserRole::Marketing) {
            return [];
        }

        if (! $user->is_team_leader) {
            return [$user->id];
        }

        return User::query()
            ->where('role', UserRole::Marketing)
            ->where(function (Builder $query) use ($user) {
                $query->where('id', $user->id)
                    ->orWhere('manager_user_id', $user->id)
                    ->when($user->team_id, fn (Builder $q) => $q->orWhere('team_id', $user->team_id));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param  Builder<Order>  $query */
    private function scopeSalesOrders(Builder $query, User $user): Builder
    {
        return $query->whereIn('sale_user_id', $this->allowedSaleIds($user));
    }

    /** @param  Builder<Order>  $query */
    private function scopeMarketingOrders(Builder $query, User $user): Builder
    {
        $marketerIds = $this->allowedMarketerIds($user);
        $sourceIds = MarketingSource::query()
            ->whereIn('marketer_user_id', $marketerIds)
            ->pluck('id');

        return $query->where(function (Builder $orders) use ($marketerIds, $sourceIds) {
            $orders->whereIn('marketer_user_id', $marketerIds)
                ->orWhereIn('marketing_source_id', $sourceIds);
        });
    }

    /** @param  Builder<Order>  $query */
    private function scopeWarehouseOrders(Builder $query, ReportFilterData $filter): Builder
    {
        return $query
            ->whereIn('delivery_status', ['waiting_waybill', 'picking_up', 'delivering', 'failed', 'returned'])
            ->when($filter->warehouseId, fn (Builder $q) => $q->where('warehouse_id', $filter->warehouseId));
    }

    /** @param  Builder<Order>  $query */
    private function scopeAccountingOrders(Builder $query): Builder
    {
        return $query->where(function (Builder $orders) {
            $orders->whereIn('delivery_status', ['delivered', 'paid', 'returned', 'failed'])
                ->orWhereIn('reconciliation_status', ['pending', 'reconciled']);
        });
    }

    /** @param  Builder<Order>  $query */
    private function scopeAllocatorOrders(Builder $query): Builder
    {
        return $query->whereNotNull('sale_user_id')->orWhereNull('sale_user_id');
    }
}
