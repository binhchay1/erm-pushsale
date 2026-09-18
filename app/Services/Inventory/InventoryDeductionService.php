<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use Illuminate\Support\Facades\DB;

class InventoryDeductionService
{
    /**
     * @return list<array{productId: int, productName: string, required: int, available: int, sufficient: bool}>
     */
    public function checkOrderStock(Order $order): array
    {
        $order->loadMissing('items.product');
        $warehouseId = $this->resolveWarehouseId($order);

        if (! $warehouseId || $order->items->isEmpty()) {
            return [];
        }

        $warnings = [];

        foreach ($order->items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $required = max(1, (int) $item->quantity);
            $available = $this->availableStock($warehouseId, (int) $item->product_id);

            $warnings[] = [
                'productId' => (int) $item->product_id,
                'productName' => $item->product_name,
                'required' => $required,
                'available' => $available,
                'sufficient' => $available >= $required,
            ];
        }

        return $warnings;
    }

    public function hasSufficientStock(Order $order): bool
    {
        $checks = $this->checkOrderStock($order);

        if ($checks === []) {
            return true;
        }

        return collect($checks)->every(fn (array $row) => $row['sufficient']);
    }

    /**
     * Trừ tồn kho khi tạo vận đơn thành công (idempotent theo order).
     */
    public function deductForOrder(Order $order, ?User $actor = null): void
    {
        $order->refresh();

        if ($order->inventory_deducted_at) {
            return;
        }

        $order->loadMissing('items.product');
        $warehouseId = $this->resolveWarehouseId($order);

        if (! $warehouseId || $order->items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $warehouseId, $actor) {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $qty = max(1, (int) $item->quantity);
                $inventory = $this->findOrCreateInventory($warehouseId, (int) $item->product_id);

                $inventory->decrement('stock_quantity', $qty);
                $inventory->refresh();

                WarehouseInventoryMovement::query()->create([
                    'warehouse_inventory_id' => $inventory->id,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id,
                    'user_id' => $actor?->id,
                    'type' => WarehouseInventoryMovement::TYPE_DEDUCTION,
                    'quantity' => -$qty,
                    'unit_cost' => (int) ($item->cost_price ?: $item->product?->cost_price ?: 0),
                    'stock_after' => $inventory->stock_quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => 'Trừ kho khi tạo vận đơn — '.$order->order_code,
                ]);
            }

            $order->update(['inventory_deducted_at' => now()]);
        });
    }

    /**
     * Chốt đơn / xuất kho được phép âm tồn — không chặn theo tồn hiện tại.
     * $confirmInsufficientStock giữ signature cũ (UI/API vẫn gửi) nhưng không còn gate.
     */
    public function assertCanClose(Order $order, bool $confirmInsufficientStock = false): void
    {
        // no-op: khách yêu cầu xuất âm; số liệu tồn được phép âm.
    }

    public function resolveWarehouseId(Order $order): ?int
    {
        if ($order->warehouse_id) {
            return (int) $order->warehouse_id;
        }

        return Warehouse::query()->orderBy('id')->value('id');
    }

    public function availableStock(int $warehouseId, int $productId): int
    {
        return (int) WarehouseInventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->sum('stock_quantity');
    }

    public function findOrCreateInventory(int $warehouseId, int $productId): WarehouseInventory
    {
        $existing = WarehouseInventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $product = Product::query()->find($productId);

        return WarehouseInventory::query()->create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'stock_quantity' => 0,
            'pending_sales_quantity' => 0,
            'uom' => $product?->uom ?? null,
        ]);
    }
}
