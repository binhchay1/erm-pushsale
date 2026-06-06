<?php

namespace App\Services\Orders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Events\OrderClosed;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderClosingService
{
    /**
     * Chốt đơn telesale → chuyển sang kho (chờ tạo vận đơn).
     *
     * @param  array<string, mixed>  $payload
     */
    public function close(Order $order, User $actor, array $payload = []): Order
    {
        if ($order->closed_at) {
            throw ValidationException::withMessages([
                'order' => 'Đơn đã được chốt trước đó.',
            ]);
        }

        if ($order->sale_user_id && $actor->isSales() && $order->sale_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'order' => 'Bạn không có quyền chốt đơn này.',
            ]);
        }

        return DB::transaction(function () use ($order, $payload) {
            $amountToCollect = (int) ($payload['amount_to_collect']
                ?? max(0, (int) $order->total - (int) $order->deposit));

            $shippingProvider = $payload['shipping_provider']
                ?? (in_array($order->shipping_method, array_keys(config('shipping_partners.providers', [])), true)
                    ? $order->shipping_method
                    : null);

            $order->update([
                'closed_at' => now(),
                'closing_status' => ClosingStatus::Closed->value,
                'operation_stage' => OperationStage::Care1->value,
                'operation_result' => $payload['operation_result'] ?? OperationResult::ClosedSuccess->value,
                'delivery_status' => DeliveryStatus::WaitingWaybill->value,
                'amount_to_collect' => $amountToCollect,
                'shipping_geo' => $payload['shipping_geo'] ?? $order->shipping_geo,
                'shipping_address' => $payload['shipping_address'] ?? $order->shipping_address,
                'warehouse_id' => $payload['warehouse_id'] ?? $order->warehouse_id,
                'shipping_provider' => $shippingProvider,
                'shipping_method' => $payload['shipping_method'] ?? $order->shipping_method,
            ]);

            event(new OrderClosed($order->fresh()));

            return $order->fresh(['items', 'warehouse']);
        });
    }
}
