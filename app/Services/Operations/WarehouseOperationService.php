<?php

namespace App\Services\Operations;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Shipping\ShipmentActionResolver;
use App\Support\OrderRevenue;
use App\Support\PhoneCarrier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** Màn Thủ kho tác nghiệp — nguồn dữ liệu thống nhất cho xuất kho, giao vận, hoàn hàng và COD. */
class WarehouseOperationService
{
    /** @var array<string, list<string>> */
    private const TAB_GROUPS = [
        'waiting_waybill' => ['waiting_waybill'],
        'deliver_now' => ['deliver_now'],
        'postpone' => ['postpone', 'delay_delivery'],
        'cancel_waybill' => ['cancel_waybill'],
        'cancel_closing' => ['cancel_closing'],
        'posted' => ['posted'],
        'picking_up' => ['picking_up'],
        'picked_up' => ['picked_up'],
        'cannot_pickup' => ['cannot_pickup'],
        'delivering' => ['delivering'],
        'cannot_deliver' => ['cannot_deliver'],
        'redelivery' => ['redelivery'],
        'delivered' => ['delivered', 'delivery_complete'],
        'partial_delivery' => ['partial_delivery', 'partial', 'delivered_partial', 'partially_delivered'],
        'paid' => ['paid'],
        'returning' => ['returning', 'refund'],
        'returned' => ['returned'],
        'compensation' => ['compensation', 'carrier_compensation'],
    ];

    public function __construct(
        private readonly InventoryDeductionService $inventory,
        private readonly ShipmentActionResolver $shipmentActions,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        $baseQuery = Order::query()
            ->whereNotNull('closed_at')
            ->applyReportFilter($filter->withoutDeliveryStatus());

        $statusTabs = $this->statusTabs($baseQuery, $filter->hideZeroStatus);
        $pageQuery = clone $baseQuery;
        $this->applyStatusTab($pageQuery, $filter->deliveryStatus);

        $summaryQuery = clone $pageQuery;
        $summary = [
            'orders' => (clone $summaryQuery)->count(),
            'grossRevenue' => (int) (clone $summaryQuery)->sum('total'),
            'codExpected' => (int) (clone $summaryQuery)->sum('amount_to_collect'),
            'codSettled' => (int) (clone $summaryQuery)->sum('settled_cod_amount'),
            'carrierCost' => (int) (clone $summaryQuery)->selectRaw('SUM('.OrderRevenue::shippingCostSql().') AS amount')->value('amount'),
            'returns' => (clone $summaryQuery)->whereIn('delivery_status', array_merge(self::TAB_GROUPS['returning'], self::TAB_GROUPS['returned']))->count(),
        ];

        $paginator = $pageQuery->with([
            'items.product', 'warehouse', 'saleUser', 'marketerUser', 'warehouseCareUser',
            'supplementalOriginPacket.relatedOrder',
            'shipments' => fn ($query) => $query->with(['statusEvents' => fn ($events) => $events->latest('occurred_at')])->latest('id'),
            'returnReceipt.lines',
            'shippingStatusEvents' => fn ($query) => $query->latest('occurred_at')->limit(10),
        ])->withCount('pendingSupplementPackets')->orderByDesc('closed_at')->paginate(
            perPage: $filter->perPage,
            columns: ['*'],
            pageName: 'page',
            page: $filter->page,
        )->withQueryString();

        return [
            'summary' => $summary,
            'rows' => [
                'data' => collect($paginator->items())->map(fn (Order $order) => $this->presentRow($order))->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
                    'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'statusTabs' => $statusTabs,
        ];
    }

    private function applyStatusTab(Builder $query, ?string $value): void
    {
        if (! $value || $value === 'all') return;
        $query->whereIn('delivery_status', self::TAB_GROUPS[$value] ?? [$value]);
    }

    /** @return list<array{value:string,label:string,count:int}> */
    private function statusTabs(Builder $baseQuery, bool $hideZero = false): array
    {
        $meta = [
            'waiting_waybill' => ['label' => 'Chờ vận đơn', 'code' => '1', 'level' => 4],
            'deliver_now' => ['label' => 'Giao ngay', 'code' => '2', 'level' => 1],
            'postpone' => ['label' => 'Hoãn giao hàng', 'code' => '3', 'level' => 1],
            'cancel_waybill' => ['label' => 'Hủy vận đơn', 'code' => '4', 'level' => 1],
            'cancel_closing' => ['label' => 'Hủy đăng đơn', 'code' => '5', 'level' => 1],
            'posted' => ['label' => 'Đã đăng', 'code' => '20', 'level' => 4],
            'picking_up' => ['label' => 'Đang lấy hàng', 'code' => '23', 'level' => 1],
            'picked_up' => ['label' => 'Đã lấy hàng', 'code' => '21', 'level' => 1],
            'cannot_pickup' => ['label' => 'Không lấy được hàng', 'code' => '22', 'level' => 1],
            'delivering' => ['label' => 'Đang giao hàng', 'code' => '30', 'level' => 1],
            'cannot_deliver' => ['label' => 'Không giao được', 'code' => '33', 'level' => 1],
            'redelivery' => ['label' => 'Yêu cầu giao lại', 'code' => '34', 'level' => 1],
            'delivered' => ['label' => 'Đã giao hàng', 'code' => '31', 'level' => 4],
            'partial_delivery' => ['label' => 'Giao hàng một phần', 'code' => '35', 'level' => 3],
            'paid' => ['label' => 'Đã thanh toán', 'code' => '32', 'level' => 1],
            'returning' => ['label' => 'Đang hoàn', 'code' => '40', 'level' => 4],
            'returned' => ['label' => 'Đã hoàn', 'code' => '41', 'level' => 4],
            'compensation' => ['label' => 'Bồi hoàn', 'code' => '50', 'level' => 1],
        ];
        $tabs = [];
        foreach (self::TAB_GROUPS as $value => $statuses) {
            $count = (clone $baseQuery)->whereIn('delivery_status', $statuses)->count();
            if (! $hideZero || $count > 0) {
                $tabs[] = ['value' => $value, 'label' => $meta[$value]['label'] ?? $value, 'count' => $count, 'code' => $meta[$value]['code'] ?? $value, 'level' => $meta[$value]['level'] ?? 1];
            }
        }
        $tabs[] = ['value' => 'all', 'label' => 'Tất cả', 'count' => (clone $baseQuery)->count(), 'code' => '0', 'level' => ''];
        return $tabs;
    }

    private function warehouseCareLabel(?string $status): ?string
    {
        return match ($status) {
            'waiting' => 'Chờ care đơn',
            'calling' => 'Đang liên hệ',
            'confirmed' => 'Đã xác nhận',
            'reschedule' => 'Hẹn giao lại',
            'complaint' => 'Khiếu nại',
            'completed' => 'Hoàn tất',
            default => $status,
        };
    }

    /** @return array<string, mixed> */
    public function presentRow(Order $order): array
    {
        /** @var Shipment|null $shipment */
        $shipment = $order->shipments->first();
        $provider = $order->shipping_provider ?: $shipment?->provider;
        $stockWarnings = $this->inventory->checkOrderStock($order);
        $hasInsufficientStock = collect($stockWarnings)->contains(fn (array $warning) => ! $warning['sufficient']);
        $actions = $this->shipmentActions->forShipment($shipment, $order);
        $isReturnFlow = in_array((string) $order->delivery_status, array_merge(self::TAB_GROUPS['returning'], self::TAB_GROUPS['returned']), true);
        $products = $order->items->map(fn ($item) => [
            'id' => (int) $item->id,
            'productId' => $item->product_id ? (int) $item->product_id : null,
            'productName' => $item->product_name,
            'sku' => $item->product?->sku,
            'itemType' => $item->item_type ?: 'product',
            'origin' => $item->origin,
            'isUpsell' => $item->item_type === 'upsell' || str_contains(strtolower((string) $item->origin), 'upsell'),
            'quantity' => max(1, (int) $item->quantity),
            'unitPrice' => (int) $item->unit_price,
            'discountAmount' => (int) $item->discount_amount,
            'lineTotal' => $item->lineTotal(),
        ])->values();
        $carrierCost = $order->shippingCost();
        $netCash = (int) $order->settled_cod_amount + (int) $order->deposit - $carrierCost;
        $warehouseCareUpdatedAt = $order->getAttribute('warehouse_care_updated_at');
        $warehouseCareUpdatedAt = $warehouseCareUpdatedAt instanceof CarbonInterface
            ? $warehouseCareUpdatedAt->toIso8601String()
            : $warehouseCareUpdatedAt;

        return [
            'id' => (string) $order->id,
            'orderCode' => $order->order_code,
            'dataArrivedAt' => $order->data_arrived_at?->toIso8601String(),
            'closedAt' => $order->closed_at?->toIso8601String(),
            'desiredDeliveryAt' => $order->desired_delivery_at?->toIso8601String(),
            'lastDeliveryEventAt' => $order->last_delivery_event_at?->toIso8601String(),
            'printedAt' => $order->printed_at?->toIso8601String(),
            'saleName' => $order->saleUser?->name,
            'saleUsername' => $order->saleUser?->email ? strstr($order->saleUser->email, '@', true) : null,
            'marketerName' => $order->marketerUser?->name,
            'warehouseCareName' => $order->warehouseCareUser?->name,
            'warehouseCareStatus' => $order->warehouse_care_status,
            'warehouseCareStatusLabel' => $this->warehouseCareLabel($order->warehouse_care_status),
            'warehouseCareNote' => $order->warehouse_care_note,
            'warehouseCareUpdatedAt' => $warehouseCareUpdatedAt,
            'lastInternalMessage' => null,
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'receiverName' => $order->receiver_name,
            'receiverPhone' => $order->receiver_phone,
            'effectiveReceiverName' => $order->effectiveReceiverName(),
            'effectiveReceiverPhone' => $order->effectiveReceiverPhone(),
            'shippingAddress' => $order->effectiveShippingAddress(),
            'shippingAddressRaw' => $order->shipping_address,
            'shippingAddress2' => $order->shipping_address_2,
            'customerNote' => $order->customer_note,
            'shippingNotes' => $order->shipping_notes,
            'warehouseId' => $order->warehouse_id,
            'warehouseName' => $order->warehouse?->name,
            'products' => $products->all(),
            'mainProducts' => $products->where('isUpsell', false)->values()->all(),
            'upsellProducts' => $products->where('isUpsell', true)->values()->all(),
            'totalQuantity' => (int) $products->sum('quantity'),
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount,
            'vat' => (int) $order->vat,
            'shippingFeeCollected' => (int) $order->shipping_fee_collected,
            'total' => (int) $order->total,
            'deposit' => (int) $order->deposit,
            'codAmount' => (int) ($order->amount_to_collect ?? max(0, (int) $order->total - (int) $order->deposit)),
            'settledCodAmount' => (int) $order->settled_cod_amount,
            'carrierServiceFee' => (int) $order->carrier_service_fee,
            'carrierReturnFee' => (int) $order->carrier_return_fee,
            'codFee' => (int) $order->cod_fee,
            'carrierOtherFee' => (int) $order->carrier_other_fee,
            'carrierCompensationAmount' => (int) $order->carrier_compensation_amount,
            'carrierCost' => $carrierCost,
            'netCash' => $netCash,
            'netRevenue' => $order->netRevenue(),
            'reconciliationStatus' => $order->reconciliation_status,
            'deliveryStatus' => DeliveryStatus::tryFrom((string) $order->delivery_status)?->label() ?? $order->delivery_status,
            'deliveryStatusValue' => $order->delivery_status,
            'shippingMethod' => $order->shipping_method,
            'shippingProvider' => $provider,
            'shippingProviderLabel' => $provider ? config("shipping_partners.providers.{$provider}.label", strtoupper($provider)) : null,
            'trackingNumber' => $shipment?->tracking_number ?: $order->tracking_number,
            'shipmentError' => $shipment?->error_message,
            'shipment' => $shipment ? [
                'id' => $shipment->id,
                'statusText' => $shipment->status_text,
                'fee' => (int) $shipment->fee,
                'returnFee' => (int) $shipment->return_fee,
                'codFee' => (int) $shipment->cod_fee,
                'codCollected' => (int) $shipment->cod_collected,
                'codRemitted' => (int) $shipment->cod_remitted,
                'compensationAmount' => (int) $shipment->compensation_amount,
                'lastEventAt' => $shipment->last_event_at?->toIso8601String(),
            ] : null,
            'statusEvents' => $order->shippingStatusEvents->map(fn ($event) => [
                'id' => $event->id, 'rawStatus' => $event->raw_status, 'mappedStatus' => $event->mapped_status,
                'location' => $event->location, 'note' => $event->note,
                'occurredAt' => $event->occurred_at?->toIso8601String(), 'financials' => $event->financials,
            ])->values()->all(),
            'inventoryDeducted' => (bool) $order->inventory_deducted_at,
            'inventoryDeductedAt' => $order->inventory_deducted_at?->toIso8601String(),
            'stockWarnings' => $stockWarnings,
            'hasInsufficientStock' => $hasInsufficientStock,
            'canCreateShipment' => $actions['canCreate'] && ! $isReturnFlow,
            'canPrintLabel' => $actions['canPrintLabel'],
            'isReturnFlow' => $isReturnFlow,
            'returnReason' => $order->return_reason,
            'returnRestockedAt' => $order->return_restocked_at?->toIso8601String(),
            'returnReceipt' => $order->returnReceipt ? [
                'id' => $order->returnReceipt->id, 'status' => $order->returnReceipt->status,
                'source' => $order->returnReceipt->source, 'receivedAt' => $order->returnReceipt->received_at?->toIso8601String(),
                'lines' => $order->returnReceipt->lines->toArray(),
            ] : null,
            'canReceiveReturn' => $isReturnFlow && ! $order->return_restocked_at,
            'canSplit' => ! $order->inventory_deducted_at && ! filled($shipment?->tracking_number),
            'carrierLabel' => PhoneCarrier::bracket($order->effectiveReceiverPhone() ?: $order->customer_phone, $order->phone_carrier),
            'phoneCarrier' => PhoneCarrier::label($order->effectiveReceiverPhone() ?: $order->customer_phone) ?? $order->phone_carrier,
            'phoneCarrierKey' => PhoneCarrier::key($order->effectiveReceiverPhone() ?: $order->customer_phone),
            'isReturningCustomer' => (bool) $order->is_returning_customer,
            'isDuplicatePhone' => (bool) $order->is_duplicate_phone,
            'awaitingLandingUpsell' => $order->isAwaitingLandingUpsell(),
            'landingUpsellHoldUntil' => $order->landing_upsell_hold_until?->toIso8601String(),
            'pendingSupplementCount' => (int) ($order->pending_supplement_packets_count ?? 0),
            'isSupplementalOrder' => $order->supplementalOriginPacket !== null,
            'supplementalOriginalOrderCode' => $order->supplementalOriginPacket?->relatedOrder?->order_code,
            'sourceType' => $order->closing_status,
            'canDeleteOrder' => blank($order->tracking_number) && $shipment === null && ! $order->inventory_deducted_at,
        ];
    }

    private function carrierBracket(?string $phone, ?string $stored = null): ?string
    {
        return PhoneCarrier::bracket($phone, $stored);
    }
}
