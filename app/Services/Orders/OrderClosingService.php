<?php

namespace App\Services\Orders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Events\OrderClosed;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Leads\LandingUpsellService;
use App\Services\Settings\FeatureSettingsService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderClosingService
{
    public function __construct(
        private readonly InventoryDeductionService $inventory,
        private readonly LandingUpsellService $landingUpsell,
        private readonly OrderOperationHistoryService $history,
        private readonly OrderCodeGenerator $orderCodes,
        private readonly FeatureSettingsService $featureSettings,
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
        $this->landingUpsell->lockFromSaleAction($order);

        return DB::transaction(function () use ($order, $payload, $actor) {
            $before = $this->history->snapshot($order);
            $amountToCollect = (int) ($payload['amount_to_collect']
                ?? max(0, (int) $order->total - (int) $order->deposit));

            $shippingProvider = $payload['shipping_provider']
                ?? (in_array($order->shipping_method, array_keys(config('shipping_partners.providers', [])), true)
                    ? $order->shipping_method
                    : null);

            $customerNote = $order->customer_note;
            if (filled($payload['note'] ?? null)) {
                $customerNote = trim(($customerNote ? $customerNote."\n" : '').$payload['note']);
            }

            $shippingNotes = $payload['shipping_notes'] ?? $order->shipping_notes;
            if (! filled($shippingNotes)) {
                $shippingNotes = $this->featureSettings->string('SettingGhiChuGiaoHangSale', '');
            }

            $order->update([
                'order_code' => $this->orderCodes->generate($order),
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
                'shipping_notes' => $shippingNotes,
                'customer_note' => $customerNote,
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

            $this->history->record(
                $fresh,
                $actor,
                OrderOperationHistory::ACTION_ORDER_CLOSED,
                $before,
                $this->history->snapshot($fresh),
                $payload['note'] ?? null,
                [
                    'amount_to_collect' => (int) $fresh->amount_to_collect,
                    'delivery_status' => $fresh->delivery_status,
                ],
            );

            return $fresh;
        });
    }
}
