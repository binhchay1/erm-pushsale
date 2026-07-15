<?php

namespace App\Services\Inventory;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarehouseInventoryMovement;
use App\Models\WarehouseReturnReceipt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Nhập hàng hoàn theo từng dòng sản phẩm. Idempotent theo order + order_item.
 * Chỉ hàng sellable mới cộng lại tồn; hỏng/mất vẫn được lưu để đối soát tài chính.
 */
class InventoryReturnService
{
    public function __construct(private readonly InventoryDeductionService $deduction) {}

    /**
     * @param list<array{order_item_id?:int,product_id?:int,received_quantity?:int,restock_quantity?:int,damaged_quantity?:int,missing_quantity?:int,condition?:string,note?:?string}>|null $lines
     */
    public function receiveReturn(
        Order $order,
        ?string $reason,
        ?User $actor = null,
        ?array $lines = null,
        string $source = WarehouseReturnReceipt::SOURCE_MANUAL,
        ?Shipment $shipment = null,
        ?string $note = null,
    ): WarehouseReturnReceipt {
        return DB::transaction(function () use ($order, $reason, $actor, $lines, $source, $shipment, $note) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            $warehouseId = $this->deduction->resolveWarehouseId($order);

            $receipt = WarehouseReturnReceipt::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'shipment_id' => $shipment?->id,
                    'warehouse_id' => $warehouseId,
                    'received_by_user_id' => $actor?->id,
                    'source' => $source,
                    'status' => 'received',
                    'reason' => $reason,
                    'note' => $note,
                    'received_at' => now(),
                ],
            );

            if (! $receipt->wasRecentlyCreated) {
                $receipt->update(array_filter([
                    'shipment_id' => $receipt->shipment_id ?: $shipment?->id,
                    'warehouse_id' => $receipt->warehouse_id ?: $warehouseId,
                    'received_by_user_id' => $actor?->id,
                    'reason' => $reason ?: $receipt->reason,
                    'note' => $note ?: $receipt->note,
                    'received_at' => $receipt->received_at ?: now(),
                ], fn ($value) => $value !== null && $value !== ''));
            }

            $byItemId = collect($lines ?? [])->keyBy(fn (array $line) => (int) ($line['order_item_id'] ?? 0));
            $byProductId = collect($lines ?? [])->keyBy(fn (array $line) => (int) ($line['product_id'] ?? 0));
            $restockedAny = false;

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $expected = max(1, (int) $item->quantity);
                $input = $byItemId->get((int) $item->id)
                    ?? $byProductId->get((int) $item->product_id)
                    ?? [];

                $received = min($expected, max(0, (int) Arr::get($input, 'received_quantity', $expected)));
                $damaged = min($received, max(0, (int) Arr::get($input, 'damaged_quantity', 0)));
                $missing = min($expected - $received, max(0, (int) Arr::get($input, 'missing_quantity', $expected - $received)));
                $condition = (string) Arr::get($input, 'condition', $damaged > 0 ? 'damaged' : 'sellable');
                $requestedRestock = Arr::has($input, 'restock_quantity')
                    ? max(0, (int) Arr::get($input, 'restock_quantity'))
                    : max(0, $received - $damaged);
                $restock = $condition === 'sellable' ? min($received - $damaged, $requestedRestock) : 0;

                $line = $receipt->lines()->firstOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'product_id' => $item->product_id,
                        'expected_quantity' => $expected,
                        'received_quantity' => $received,
                        'restock_quantity' => 0,
                        'damaged_quantity' => $damaged,
                        'missing_quantity' => $missing,
                        'condition' => $condition,
                        'note' => Arr::get($input, 'note'),
                    ],
                );

                // Chỉ cộng phần tăng thêm để webhook retry không tăng tồn lần hai.
                $alreadyRestocked = (int) $line->restock_quantity;
                $delta = max(0, $restock - $alreadyRestocked);

                if ($delta > 0 && $order->inventory_deducted_at && $warehouseId) {
                    $inventory = $this->deduction->findOrCreateInventory($warehouseId, (int) $item->product_id);
                    $inventory->increment('stock_quantity', $delta);
                    $inventory->refresh();

                    WarehouseInventoryMovement::query()->create([
                        'warehouse_inventory_id' => $inventory->id,
                        'warehouse_id' => $warehouseId,
                        'product_id' => $item->product_id,
                        'user_id' => $actor?->id,
                        'type' => WarehouseInventoryMovement::TYPE_RETURN,
                        'quantity' => $delta,
                        'stock_after' => $inventory->stock_quantity,
                        'reference_type' => 'warehouse_return_receipt',
                        'reference_id' => $receipt->id,
                        'note' => 'Nhập hàng hoàn '.$order->order_code.' — '.($reason ?: 'không ghi lý do'),
                    ]);
                    $restockedAny = true;
                }

                $line->update([
                    'expected_quantity' => $expected,
                    'received_quantity' => max((int) $line->received_quantity, $received),
                    'restock_quantity' => max($alreadyRestocked, $restock),
                    'damaged_quantity' => max((int) $line->damaged_quantity, $damaged),
                    'missing_quantity' => max((int) $line->missing_quantity, $missing),
                    'condition' => $condition,
                    'note' => Arr::get($input, 'note', $line->note),
                ]);
            }

            $order->update([
                'delivery_status' => DeliveryStatus::Returned->value,
                'return_reason' => $reason ?: $order->return_reason,
                'return_restocked_at' => ($restockedAny || $receipt->lines()->where('restock_quantity', '>', 0)->exists())
                    ? ($order->return_restocked_at ?: now())
                    : $order->return_restocked_at,
                'last_delivery_event_at' => now(),
            ]);

            return $receipt->fresh(['lines', 'order', 'shipment']);
        });
    }
}
