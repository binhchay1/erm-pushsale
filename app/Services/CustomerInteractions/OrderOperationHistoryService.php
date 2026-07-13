<?php

namespace App\Services\CustomerInteractions;

use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;

final class OrderOperationHistoryService
{
    /** @return array<string, mixed> */
    public function snapshot(Order $order): array
    {
        return [
            'operation_stage' => $order->operation_stage,
            'operation_result' => $order->operation_result,
            'next_operation_at' => $order->next_operation_at,
            'contact_count' => (int) $order->contact_count,
            'closing_status' => $order->closing_status,
            'closed_at' => $order->closed_at,
        ];
    }

    /**
     * Snapshot nghiệp vụ để modal lịch sử tái hiện đúng nội dung tại thời điểm tác nghiệp.
     * Chỉ đọc dữ liệu thật của đơn; không tạo dòng demo.
     *
     * @return array<string, mixed>
     */
    public function orderSnapshot(Order $order): array
    {
        $order->loadMissing(['items', 'saleUser', 'marketingSource', 'warehouse']);

        return [
            'order_id' => (int) $order->id,
            'order_code' => $order->closed_at ? $order->order_code : null,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'receiver_name' => $order->effectiveReceiverName(),
            'receiver_phone' => $order->effectiveReceiverPhone(),
            'address' => $order->effectiveShippingAddress(),
            'source' => $order->marketingSource?->name,
            'sale' => $order->saleUser?->name,
            'warehouse' => $order->warehouse?->name,
            'shipping_method' => $order->shipping_method,
            'shipping_provider' => $order->shipping_provider,
            'carrier_name' => $order->carrier_name,
            'shipping_service' => data_get($order->shipping_geo, 'service'),
            'shipping_notes' => $order->shipping_notes,
            'tracking_number' => $order->tracking_number,
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount,
            'vat' => (int) $order->vat,
            'shipping_fee_collected' => (int) $order->shipping_fee_collected,
            'total' => (int) $order->total,
            'deposit' => (int) $order->deposit,
            'amount_to_collect' => (int) $order->amount_to_collect,
            'desired_delivery_at' => $order->desired_delivery_at?->toIso8601String(),
            'products' => $order->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
                'discount_amount' => (int) ($item->discount_amount ?? 0),
                'item_type' => $item->item_type ?? 'product',
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Order $order,
        User $actor,
        string $action,
        array $before,
        array $after,
        ?string $note = null,
        array $metadata = [],
    ): OrderOperationHistory {
        $metadata = array_replace_recursive([
            'order_snapshot' => $this->orderSnapshot($order),
        ], $metadata);

        return OrderOperationHistory::query()->create([
            'company_id' => $order->company_id ?? $actor->company_id,
            'order_id' => $order->id,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role?->value,
            'action' => $action,
            'operation_stage_before' => $before['operation_stage'] ?? null,
            'operation_stage_after' => $after['operation_stage'] ?? null,
            'operation_result' => $after['operation_result'] ?? null,
            'next_operation_at' => $after['next_operation_at'] ?? null,
            'note' => filled($note) ? trim((string) $note) : null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
