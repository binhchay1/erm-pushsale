<?php

namespace App\Services\Orders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Events\OrderClosed;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\InventoryDeductionService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderClosingService
{
    public function __construct(
        private readonly InventoryDeductionService $inventory,
    ) {}

    /**
     * Chốt đơn telesale → chuyển sang kho (chờ tạo vận đơn).
     *
     * @param  array<string, mixed>  $payload
     */
    public function close(Order $order, User $actor, array $payload = []): Order
    {
        if ($order->closed_at) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.already_closed'),
            ]);
        }

        // Không cho chốt đơn đã hủy / đã ngừng tác nghiệp — đồng nhất với điều kiện ẩn nút "Đóng đơn".
        if (in_array($order->closing_status, [ClosingStatus::Cancelled->value], true)
            || $order->delivery_status === DeliveryStatus::CancelClosing->value) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.cannot_close_cancelled'),
            ]);
        }

        if ($order->sale_user_id && $actor->isSales() && $order->sale_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.no_permission_close'),
            ]);
        }

        $confirmInsufficient = (bool) ($payload['confirm_insufficient_stock'] ?? false);
        $this->inventory->assertCanClose($order, $confirmInsufficient);

        return DB::transaction(function () use ($order, $payload, $actor) {
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

            $fresh = $order->fresh(['items', 'warehouse']);

            ActivityLogger::log(
                ActivityLogger::ORDER_CLOSED,
                $fresh,
                [
                    'amount_to_collect' => $fresh->amount_to_collect,
                    'delivery_status' => $fresh->delivery_status,
                ],
                $fresh->order_code ?? ('#'.$fresh->id),
                $actor,
            );

            return $fresh;
        });
    }
}
