<?php

namespace App\Services\Inventory;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\WarehouseInventoryMovement;
use Illuminate\Support\Facades\DB;

/**
 * Xử lý hàng hoàn: ghi nhận lý do hoàn + cộng lại tồn kho (idempotent theo order).
 */
class InventoryReturnService
{
    public function __construct(
        private readonly InventoryDeductionService $deduction,
    ) {}

    public function receiveReturn(Order $order, ?string $reason, ?User $actor = null): void
    {
        $order->refresh();
        $order->loadMissing('items');

        DB::transaction(function () use ($order, $reason, $actor) {
            $updates = [
                'delivery_status' => DeliveryStatus::Returned->value,
                'return_reason' => $reason ?: $order->return_reason,
            ];

            // Chỉ cộng lại kho nếu đơn đã từng trừ kho và chưa nhập hoàn trước đó
            if ($order->inventory_deducted_at && ! $order->return_restocked_at) {
                $warehouseId = $this->deduction->resolveWarehouseId($order);

                if ($warehouseId) {
                    foreach ($order->items as $item) {
                        if (! $item->product_id) {
                            continue;
                        }

                        $qty = max(1, (int) $item->quantity);
                        $inventory = $this->deduction->findOrCreateInventory($warehouseId, (int) $item->product_id);

                        $inventory->increment('stock_quantity', $qty);
                        $inventory->refresh();

                        WarehouseInventoryMovement::query()->create([
                            'warehouse_inventory_id' => $inventory->id,
                            'warehouse_id' => $warehouseId,
                            'product_id' => $item->product_id,
                            'user_id' => $actor?->id,
                            'type' => WarehouseInventoryMovement::TYPE_RETURN,
                            'quantity' => $qty,
                            'stock_after' => $inventory->stock_quantity,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'note' => 'Nhập kho hàng hoàn — '.$order->order_code
                                .($reason ? ' ('.$reason.')' : ''),
                        ]);
                    }

                    $updates['return_restocked_at'] = now();
                }
            }

            $order->update($updates);
        });
    }
}
