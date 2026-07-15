<?php

namespace App\Services\Warehouse;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Pushsale\PhoneBlacklist;
use App\Models\User;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Inventory\InventoryReturnService;
use App\Services\Leads\LeadOrderFactory;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseOrderActionService
{
    public function __construct(
        private readonly LeadOrderFactory $factory,
        private readonly InventoryReturnService $returns,
        private readonly OrderOperationHistoryService $history,
    ) {}

    public function updateDesiredDelivery(Order $order, ?string $date, ?User $actor): Order
    {
        $order->update(['desired_delivery_at' => $date ?: null]);
        $this->log($order, $actor, 'warehouse_desired_delivery_updated', ['desired_delivery_at' => $date]);
        return $order->fresh();
    }

    public function updateBlacklist(Order $order, string $phone, string $reason, ?User $actor): void
    {
        PhoneBlacklist::query()->updateOrCreate(
            ['phone' => preg_replace('/\D+/', '', $phone)],
            [
                'reason' => $reason,
                'order_id' => $order->id,
                'creation_type' => 'warehouse',
                'created_by_user_id' => $actor?->id,
                'updated_by_user_id' => $actor?->id,
            ],
        );
        $this->log($order, $actor, 'warehouse_phone_blacklisted', ['phone' => $phone, 'reason' => $reason]);
    }

    public function updateCare(Order $order, ?string $status, ?string $note, ?User $actor): Order
    {
        $order->update([
            'warehouse_care_status' => $status ?: null,
            'warehouse_care_note' => $note ?: null,
            'warehouse_care_user_id' => $actor?->id,
        ]);
        $this->log($order, $actor, 'warehouse_care_updated', compact('status', 'note'));
        return $order->fresh();
    }

    public function updateDeliveryStatus(Order $order, string $status, ?string $note, ?int $collectedAmount, ?User $actor): Order
    {
        $enum = DeliveryStatus::tryFrom($status);
        if (! $enum) {
            throw ValidationException::withMessages(['delivery_status' => 'Trạng thái giao hàng không hợp lệ.']);
        }

        $updates = [
            'delivery_status' => $enum->value,
            'last_delivery_event_at' => now(),
            'shipping_notes' => $note ?: $order->shipping_notes,
        ];
        if ($collectedAmount !== null) {
            $updates['settled_cod_amount'] = max(0, $collectedAmount);
        }
        if ($enum === DeliveryStatus::Returned && ! $order->return_reason) {
            $updates['return_reason'] = $note ?: 'Cập nhật thủ công từ kho';
        }

        $order->update($updates);
        $this->log($order, $actor, 'warehouse_delivery_status_updated', [
            'delivery_status' => $status,
            'note' => $note,
            'collected_amount' => $collectedAmount,
        ]);
        return $order->fresh();
    }

    /** @param array<string,mixed> $payload */
    public function updateOrder(Order $order, array $payload, ?User $actor): Order
    {
        if ($order->inventory_deducted_at && isset($payload['items'])) {
            throw ValidationException::withMessages(['items' => 'Đơn đã xuất kho; không thể thay đổi sản phẩm. Hãy xử lý hoàn/tách bằng chứng từ kho.']);
        }

        return DB::transaction(function () use ($order, $payload, $actor) {
            $before = $this->history->snapshot($order);
            if (is_array($payload['items'] ?? null)) {
                $order->items()->delete();
                foreach ($this->factory->buildItemRows($payload['items'], 'warehouse') as $row) {
                    $order->items()->create($row);
                }
            }

            foreach ([
                'customer_name', 'customer_phone', 'receiver_name', 'receiver_phone',
                'shipping_address', 'shipping_address_2', 'shipping_notes', 'customer_note',
                'warehouse_id', 'shipping_provider', 'shipping_method', 'discount', 'vat',
                'shipping_fee_collected', 'deposit',
            ] as $field) {
                if (array_key_exists($field, $payload)) {
                    $order->{$field} = $payload[$field] === '' ? null : $payload[$field];
                }
            }
            $order->save();
            $fresh = $this->factory->syncTotals($order->fresh(['items']));
            $this->log($fresh, $actor, 'warehouse_order_updated', ['fields' => array_keys($payload)]);
            return $fresh->fresh(['items.product', 'warehouse']);
        });
    }

    /** @param list<array{order_item_id:int,quantity:int}> $lines */
    public function split(Order $order, array $lines, ?User $actor): Order
    {
        if ($order->inventory_deducted_at || $order->shipments()->whereNotNull('tracking_number')->exists()) {
            throw ValidationException::withMessages(['order' => 'Chỉ được tách đơn trước khi xuất kho/tạo vận đơn.']);
        }

        return DB::transaction(function () use ($order, $lines, $actor) {
            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            $requested = collect($lines)->keyBy('order_item_id');
            if ($requested->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Chưa chọn sản phẩm cần tách.']);
            }

            $split = $order->replicate([
                'order_code', 'tracking_number', 'inventory_deducted_at', 'return_restocked_at',
                'printed_at', 'settlement_matched_at', 'last_delivery_event_at',
            ]);
            $split->order_code = $this->nextSplitCode($order);
            $split->tracking_number = null;
            $split->inventory_deducted_at = null;
            $split->return_restocked_at = null;
            $split->printed_at = null;
            $split->delivery_status = DeliveryStatus::WaitingWaybill->value;
            $split->reconciliation_status = 'pending';
            $split->settled_cod_amount = 0;
            $split->carrier_service_fee = 0;
            $split->carrier_return_fee = 0;
            $split->carrier_other_fee = 0;
            $split->carrier_compensation_amount = 0;
            $split->cod_fee = 0;
            $split->save();

            foreach ($order->items as $item) {
                $quantity = max(0, (int) ($requested->get($item->id)['quantity'] ?? 0));
                if ($quantity <= 0) continue;
                if ($quantity > (int) $item->quantity) {
                    throw ValidationException::withMessages(['items' => "Số lượng tách của {$item->product_name} vượt quá số lượng đơn."]);
                }

                $newItem = $item->replicate(['order_id']);
                $newItem->order_id = $split->id;
                $newItem->quantity = $quantity;
                $newItem->save();

                if ($quantity === (int) $item->quantity) {
                    $item->delete();
                } else {
                    $item->decrement('quantity', $quantity);
                }
            }

            if (! $split->items()->exists() || ! $order->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Phải giữ lại ít nhất một sản phẩm ở cả đơn gốc và đơn tách.']);
            }

            $this->factory->syncTotals($order->fresh(['items']));
            $split = $this->factory->syncTotals($split->fresh(['items']));
            $this->log($order, $actor, 'warehouse_order_split', ['split_order_id' => $split->id, 'split_order_code' => $split->order_code]);
            return $split->fresh(['items.product']);
        });
    }

    public function markPrinted(Order $order, ?User $actor): Order
    {
        $order->update(['printed_at' => now()]);
        $this->log($order, $actor, 'warehouse_order_printed', []);
        return $order->fresh();
    }

    /** @param list<array<string,mixed>>|null $lines */
    public function receiveReturn(Order $order, ?string $reason, ?string $note, ?array $lines, ?User $actor): void
    {
        $shipment = $order->shipments()->latest('id')->first();
        $this->returns->receiveReturn($order, $reason, $actor, $lines, 'manual', $shipment, $note);
        $this->log($order, $actor, 'warehouse_return_received', ['reason' => $reason]);
    }

    private function nextSplitCode(Order $order): string
    {
        $base = $order->order_code ?: 'PS'.$order->id;
        $index = 1;
        do {
            $code = $base.'-T'.$index++;
        } while (Order::query()->where('order_code', $code)->exists());
        return $code;
    }

    /** @param array<string,mixed> $metadata */
    private function log(Order $order, ?User $actor, string $action, array $metadata): void
    {
        ActivityLogger::log($action, $order, $metadata, $order->order_code ?? ('#'.$order->id), $actor);
    }
}
