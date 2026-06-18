<?php

namespace App\Services\Operations;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\Order;
use App\Services\Inventory\InventoryDeductionService;
use Illuminate\Support\Collection;

/**
 * Presenter — chuyển Order model → payload Inertia (Sale / Kế toán / Thủ kho).
 */
final class OrderOperationPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Order $order): array
    {
        $stage = OperationStage::tryFrom($order->operation_stage);
        $result = OperationResult::tryFromStored($order->operation_result);
        $closing = $order->closed_at
            ? ClosingStatus::Closed
            : (ClosingStatus::tryFrom((string) ($order->closing_status ?? '')) ?? ClosingStatus::Open);

        return [
            'id' => (string) $order->id,
            'orderCode' => $order->order_code,
            'sourceName' => $order->marketingSource?->name ?? '—',
            'dataArrivedAt' => $order->data_arrived_at?->toIso8601String(),
            'saleId' => (string) $order->sale_user_id,
            'saleName' => $order->saleUser?->name ?? '—',
            'saleGroup' => $order->team?->name ?? '—',
            'assignedAt' => $order->assigned_at?->toIso8601String(),
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'phoneCarrier' => $order->phone_carrier,
            'customerNote' => $order->customer_note,
            'shippingAddress' => $order->shipping_address,
            'currentOperation' => $stage?->label() ?? $order->operation_stage,
            'operationResult' => $result?->label() ?? $order->operation_result,
            'operationResultValue' => $result?->value ?? $order->operation_result,
            'operationStage' => $order->operation_stage,
            'closingStatus' => $closing->value,
            'closingStatusLabel' => $closing->label(),
            'nextOperationAt' => $order->next_operation_at?->toIso8601String(),
            'contactCount' => (int) $order->contact_count,
            'canCall' => SaleOperationPolicy::canCall($order),
            'canChangeStatus' => SaleOperationPolicy::canChangeStatus($order),
            'products' => $order->items->map(fn ($item) => [
                'productId' => (string) $item->product_id,
                'productName' => $item->product_name,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unit_price,
            ])->values()->all(),
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'vat' => $order->vat,
            'shippingFeeCollected' => $order->shipping_fee_collected,
            'total' => $order->total,
            'deposit' => $order->deposit,
            'deliveryStatus' => DeliveryStatus::tryFrom($order->delivery_status)?->label() ?? $order->delivery_status,
            'deliveryStatusValue' => $order->delivery_status,
            'desiredDeliveryAt' => $order->desired_delivery_at?->toDateString(),
            'warehouseName' => $order->warehouse?->name,
            'carrierName' => $order->carrier_name,
            'trackingNumber' => $order->tracking_number,
            'accountingNotes' => $order->accounting_notes,
            'internalReconNote' => $order->internal_recon_note,
            'amountToCollect' => $order->amount_to_collect,
            'carrierServiceFee' => $order->carrier_service_fee,
            'shippingSupportFee' => $order->shipping_support_fee,
            'isReturningCustomer' => $order->is_returning_customer,
            'isDuplicatePhone' => $order->is_duplicate_phone,
            'closedAt' => $order->closed_at?->toIso8601String(),
            'carePersonName' => $order->saleUser?->name,
            'stockWarnings' => app(InventoryDeductionService::class)->checkOrderStock($order),
            'hasInsufficientStock' => ! app(InventoryDeductionService::class)->hasSufficientStock($order),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $orders): array
    {
        return $orders->map(fn (Order $o) => self::toArray($o))->values()->all();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{status: string, label: string, count: int}>
     */
    public static function statusTabs(Collection $orders, bool $hideZero): array
    {
        $tabs = [];
        $allCount = $orders->count();
        $tabs[] = ['status' => 'all', 'label' => __('operations.all'), 'count' => $allCount, 'total' => $allCount];

        foreach (OperationStage::cases() as $stage) {
            $count = $orders->where('operation_stage', $stage->value)->count();
            if ($hideZero && $count === 0) {
                continue;
            }
            $tabs[] = [
                'status' => $stage->value,
                'label' => $stage->label(),
                'count' => $count,
                'total' => $allCount,
            ];
        }

        return $tabs;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{status: string, label: string, count: int}>
     */
    public static function accountingStatusTabs(Collection $orders, bool $hideZero): array
    {
        $tabs = [];
        $allCount = $orders->count();
        $tabs[] = ['status' => 'all', 'label' => __('operations.all'), 'count' => $allCount];

        foreach (DeliveryStatus::cases() as $status) {
            $count = $orders->where('delivery_status', $status->value)->count();
            if ($hideZero && $count === 0) {
                continue;
            }
            $tabs[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
            ];
        }

        return $tabs;
    }
}
