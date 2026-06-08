<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryIntakeService
{
    public function intake(
        int $warehouseId,
        int $productId,
        int $quantity,
        User $user,
        ?string $note = null,
    ): WarehouseInventoryMovement {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng nhập phải lớn hơn 0.',
            ]);
        }

        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Kho không tồn tại.',
            ]);
        }

        if (! Product::query()->whereKey($productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Sản phẩm không tồn tại.',
            ]);
        }

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $user, $note) {
            $inventory = WarehouseInventory::query()->firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId],
                ['stock_quantity' => 0, 'pending_sales_quantity' => 0],
            );

            $inventory->increment('stock_quantity', $quantity);
            $inventory->refresh();

            return WarehouseInventoryMovement::query()->create([
                'warehouse_inventory_id' => $inventory->id,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'user_id' => $user->id,
                'type' => WarehouseInventoryMovement::TYPE_INTAKE,
                'quantity' => $quantity,
                'stock_after' => $inventory->stock_quantity,
                'note' => $note,
            ]);
        });
    }

    /** @return list<array<string, mixed>> */
    public function recentMovements(int $limit = 20): array
    {
        return WarehouseInventoryMovement::query()
            ->with(['warehouse:id,name', 'product:id,name,sku', 'user:id,name'])
            ->where('type', WarehouseInventoryMovement::TYPE_INTAKE)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (WarehouseInventoryMovement $m) => [
                'id' => (string) $m->id,
                'createdAt' => $m->created_at?->format('d/m/Y H:i'),
                'warehouseName' => $m->warehouse->name,
                'productName' => $m->product->name,
                'sku' => $m->product->sku,
                'quantity' => $m->quantity,
                'stockAfter' => $m->stock_after,
                'userName' => $m->user?->name ?? '—',
                'note' => $m->note,
            ])
            ->all();
    }
}
