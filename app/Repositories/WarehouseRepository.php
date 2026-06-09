<?php

namespace App\Repositories;

use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Support\Collection;

class WarehouseRepository
{
    /** Danh sách kho cho bộ lọc / select. */
    public function options(): Collection
    {
        return Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    /** Danh sách kho kèm quản kho + số mặt hàng. */
    public function allWithManagerAndCounts(): Collection
    {
        return Warehouse::query()
            ->withCount('inventories')
            ->with('manager:id,name')
            ->latest('id')
            ->get();
    }

    /** Tồn kho của một kho, lọc theo tên/SKU sản phẩm. */
    public function inventoriesOf(int $warehouseId, string $search = ''): Collection
    {
        return WarehouseInventory::query()
            ->where('warehouse_id', $warehouseId)
            ->with('product:id,name,sku')
            ->when($search !== '', fn ($q) => $q->whereHas('product', function ($qq) use ($search) {
                $term = '%'.$search.'%';
                $qq->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            }))
            ->orderByDesc('id')
            ->get();
    }

    public function deleteInventoriesOfWarehouse(int $warehouseId): void
    {
        WarehouseInventory::query()->where('warehouse_id', $warehouseId)->delete();
    }

    public function deleteInventoriesOfProduct(int $productId): void
    {
        WarehouseInventory::query()->where('product_id', $productId)->delete();
    }
}
