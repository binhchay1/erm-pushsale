<?php

namespace App\Services\CustomerInteractions;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\Order;
use Illuminate\Support\Collection;

final class CustomerPurchaseHistoryPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Order $order, int|string $selectedOrderId): array
    {
        $stage = OperationStage::tryFrom((string) $order->operation_stage);
        $result = OperationResult::tryFromStored($order->operation_result);
        $closing = $order->closed_at
            ? ClosingStatus::Closed
            : (ClosingStatus::tryFrom((string) ($order->closing_status ?? '')) ?? ClosingStatus::Open);
        $delivery = DeliveryStatus::tryFrom((string) $order->delivery_status);

        return [
            'id' => (string) $order->id,
            'isSelected' => (string) $order->id === (string) $selectedOrderId,
            'orderCode' => $order->order_code,
            'sourceName' => $order->marketingSource?->name,
            'saleName' => $order->saleUser?->name,
            'teamName' => $order->team?->name,
            'warehouseName' => $order->warehouse?->name,
            'dataArrivedAt' => $order->data_arrived_at?->toIso8601String(),
            'assignedAt' => $order->assigned_at?->toIso8601String(),
            'closedAt' => $order->closed_at?->toIso8601String(),
            'createdAt' => $order->created_at?->toIso8601String(),
            'desiredDeliveryAt' => $order->desired_delivery_at?->toIso8601String(),
            'nextOperationAt' => $order->next_operation_at?->toIso8601String(),
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'address' => $order->effectiveShippingAddress(),
            'receiverName' => $order->effectiveReceiverName(),
            'receiverPhone' => $order->effectiveReceiverPhone(),
            'customerNote' => $order->customer_note,
            'shippingNotes' => $order->shipping_notes,
            'accountingNotes' => $order->accounting_notes,
            'internalReconNote' => $order->internal_recon_note,
            'operationStage' => $stage?->label() ?? $order->operation_stage,
            'operationResult' => $result?->label() ?? $order->operation_result,
            'closingStatus' => $closing->value,
            'closingStatusLabel' => $closing->label(),
            'deliveryStatus' => $delivery?->label() ?? $order->delivery_status,
            'deliveryStatusValue' => $order->delivery_status,
            'contactCount' => (int) $order->contact_count,
            'shippingMethod' => $order->shipping_method,
            'shippingProvider' => $order->shipping_provider,
            'carrierName' => $order->carrier_name,
            'trackingNumber' => $order->tracking_number,
            'reconciliationStatus' => $order->reconciliation_status,
            'returnReason' => $order->return_reason,
            'products' => $order->items->map(fn ($item) => [
                'id' => (string) $item->id,
                'name' => $item->product_name,
                'type' => $item->item_type,
                'quantity' => (int) $item->quantity,
                'unitPrice' => (int) $item->unit_price,
                'discount' => (int) $item->discount_amount,
                'lineTotal' => $item->lineTotal(),
            ])->values()->all(),
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount,
            'vat' => (int) $order->vat,
            'shippingFeeCollected' => (int) $order->shipping_fee_collected,
            'total' => (int) $order->total,
            'deposit' => (int) $order->deposit,
            'amountToCollect' => (int) $order->amount_to_collect,
        ];
    }

    /**
     * @param Collection<int, Order> $orders
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $orders, int|string $selectedOrderId): array
    {
        return $orders
            ->map(fn (Order $order) => self::toArray($order, $selectedOrderId))
            ->values()
            ->all();
    }
}
