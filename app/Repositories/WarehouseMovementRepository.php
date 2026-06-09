<?php

namespace App\Repositories;

use App\Models\WarehouseInventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WarehouseMovementRepository
{
    /**
     * Phiếu nhập / xuất gần nhất (cho màn Tồn kho).
     *
     * @param  list<string>  $types
     */
    public function recent(array $types = [], int $limit = 20): Collection
    {
        return WarehouseInventoryMovement::query()
            ->with(['warehouse:id,name', 'product:id,name,sku', 'user:id,name', 'approver:id,name'])
            ->when($types !== [], fn ($q) => $q->whereIn('type', $types))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Lịch sử nhập xuất kho đầy đủ (chỉ admin xem) — lọc theo kho, sản phẩm, loại, thời gian.
     *
     * @param  array{warehouse_id?: int|null, product_id?: int|null, type?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function paginatedHistory(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return WarehouseInventoryMovement::query()
            ->with(['warehouse:id,name', 'product:id,name,sku', 'user:id,name', 'approver:id,name'])
            ->when($filters['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
