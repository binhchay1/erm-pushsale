<?php

namespace App\Repositories;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class OrderRepository
{
    public function total(?int $saleUserId = null): int
    {
        return Order::query()
            ->when($saleUserId, fn ($q) => $q->where('sale_user_id', $saleUserId))
            ->count();
    }

    public function deliveredTotal(): int
    {
        return Order::query()->whereIn('delivery_status', DeliveryStatus::revenueEligible())->count();
    }

    public function countOnDay(Carbon $day): int
    {
        return Order::query()->whereDate('created_at', $day)->count();
    }

    public function revenueOnDay(Carbon $day): int
    {
        return (int) Order::query()
            ->whereDate('created_at', $day)
            ->whereIn('delivery_status', DeliveryStatus::revenueEligible())
            ->sum('total');
    }

    public function arrivedSinceCount(Carbon $since, ?int $saleUserId = null): int
    {
        return Order::query()
            ->when($saleUserId, fn ($q) => $q->where('sale_user_id', $saleUserId))
            ->where('data_arrived_at', '>=', $since)
            ->count();
    }

    public function existsForWarehouse(int $warehouseId): bool
    {
        return Order::query()->where('warehouse_id', $warehouseId)->exists();
    }

    /** Danh sách đơn cho API, lọc theo sale và từ khóa. */
    public function paginatedApiList(?int $saleUserId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with(['saleUser', 'marketingSource'])
            ->when($saleUserId, fn ($q) => $q->where('sale_user_id', $saleUserId))
            ->when($search, function ($q, $search) {
                $term = '%'.$search.'%';
                $q->where(fn ($inner) => $inner
                    ->where('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhere('order_code', 'like', $term));
            })
            ->latest('data_arrived_at')
            ->paginate($perPage);
    }
}
