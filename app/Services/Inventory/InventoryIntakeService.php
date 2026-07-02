<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Repositories\WarehouseMovementRepository;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nhập / xuất kho thủ công — người thực hiện + trưởng kho ký duyệt.
 */
class InventoryIntakeService
{
    public function __construct(
        private readonly WarehouseMovementRepository $movements,
    ) {}

    public function intake(
        int $warehouseId,
        int $productId,
        int $quantity,
        User $user,
        ?string $note = null,
        ?int $approvedByUserId = null,
    ): WarehouseInventoryMovement {
        $this->assertValidInput($warehouseId, $productId, $quantity);

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $user, $note, $approvedByUserId) {
            $inventory = $this->inventoryFor($warehouseId, $productId);

            $inventory->increment('stock_quantity', $quantity);
            $inventory->refresh();

            return $this->logMovement(
                $inventory,
                WarehouseInventoryMovement::TYPE_INTAKE,
                $quantity,
                $user,
                $note,
                $approvedByUserId,
            );
        });
    }

    public function export(
        int $warehouseId,
        int $productId,
        int $quantity,
        User $user,
        ?string $note = null,
        ?int $approvedByUserId = null,
    ): WarehouseInventoryMovement {
        $this->assertValidInput($warehouseId, $productId, $quantity);

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $user, $note, $approvedByUserId) {
            $inventory = $this->inventoryFor($warehouseId, $productId);

            if ($inventory->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Tồn kho hiện chỉ còn {$inventory->stock_quantity} — không đủ để xuất {$quantity}.",
                ]);
            }

            $inventory->decrement('stock_quantity', $quantity);
            $inventory->refresh();

            return $this->logMovement(
                $inventory,
                WarehouseInventoryMovement::TYPE_EXPORT,
                -$quantity,
                $user,
                $note,
                $approvedByUserId,
            );
        });
    }

    /** Phiếu nhập / xuất thủ công gần nhất cho màn Tồn kho. */
    public function recentMovements(int $limit = 20): array
    {
        return $this->movements
            ->recent([WarehouseInventoryMovement::TYPE_INTAKE, WarehouseInventoryMovement::TYPE_EXPORT], $limit)
            ->map(fn (WarehouseInventoryMovement $m) => [
                'id' => (string) $m->id,
                'createdAt' => $m->created_at?->format('d/m/Y H:i'),
                'type' => $m->type,
                'typeLabel' => WarehouseInventoryMovement::typeLabel($m->type),
                'warehouseName' => $m->warehouse->name,
                'productName' => $m->product->name,
                'sku' => $m->product->sku,
                'quantity' => $m->quantity,
                'stockAfter' => $m->stock_after,
                'userName' => $m->user?->name ?? '—',
                'approverName' => $m->approver?->name,
                'note' => $m->note,
            ])
            ->all();
    }

    private function assertValidInput(int $warehouseId, int $productId, int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Số lượng phải lớn hơn 0.',
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
    }

    private function inventoryFor(int $warehouseId, int $productId): WarehouseInventory
    {
        return WarehouseInventory::query()->firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_id' => $productId],
            ['stock_quantity' => 0, 'pending_sales_quantity' => 0],
        );
    }

    private function logMovement(
        WarehouseInventory $inventory,
        string $type,
        int $quantity,
        User $user,
        ?string $note,
        ?int $approvedByUserId,
    ): WarehouseInventoryMovement {
        $movement = WarehouseInventoryMovement::query()->create([
            'warehouse_inventory_id' => $inventory->id,
            'warehouse_id' => $inventory->warehouse_id,
            'product_id' => $inventory->product_id,
            'user_id' => $user->id,
            'approved_by_user_id' => $approvedByUserId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $inventory->stock_quantity,
            'note' => $note,
        ]);

        if ($approvedByUserId) {
            ActivityLogger::log(
                ActivityLogger::INVENTORY_MOVEMENT_APPROVED,
                $movement,
                [
                    'type' => $type,
                    'quantity' => $quantity,
                    'warehouse_id' => $inventory->warehouse_id,
                    'product_id' => $inventory->product_id,
                ],
                WarehouseInventoryMovement::typeLabel($type),
                User::query()->find($approvedByUserId),
            );
        }

        return $movement;
    }
}
