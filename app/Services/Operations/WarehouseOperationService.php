<?php

namespace App\Services\Operations;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Shipping\ShipmentActionResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Màn "Thủ kho tác nghiệp" — chỉ hiển thị dữ liệu phục vụ xuất kho / vận đơn:
 * mã đơn, khách hàng (in vận đơn), sản phẩm (SKU, số lượng), COD, trạng thái VC.
 */
class WarehouseOperationService
{
    /**
     * Tab gom nhóm trạng thái VC cho thủ kho: kho đi / kho hoàn tách bạch,
     * trong đó "Đơn hoàn" là tab riêng theo yêu cầu nghiệp vụ.
     * Nhãn hiển thị được dịch theo locale qua operations.warehouse_tabs.*
     *
     * @var array<string, list<string>>
     */
    private const TAB_GROUPS = [
        'waiting' => ['waiting_waybill', 'posted'],
        'pickup' => ['picking_up', 'cannot_pickup'],
        'delivering' => ['delivering', 'deliver_now', 'redelivery'],
        'delivered' => ['delivered', 'delivery_complete'],
        'paid' => ['paid'],
        'returns' => ['returning', 'returned', 'refund', 'cannot_deliver'],
        'cancelled' => ['cancel_waybill', 'cancel_closing'],
    ];

    public function __construct(
        private readonly InventoryDeductionService $inventory,
        private readonly ShipmentActionResolver $shipmentActions,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        // Delivery tabs are grouped values (waiting/pickup/returns...), therefore the
        // base report scope deliberately omits delivery_status. Each tab is applied in
        // SQL below so the page can paginate without loading all closed orders into PHP.
        $baseQuery = Order::query()
            ->whereNotNull('closed_at')
            ->applyReportFilter($filter->withoutDeliveryStatus());

        $statusTabs = $this->statusTabs($baseQuery, $filter->hideZeroStatus);

        $pageQuery = clone $baseQuery;
        $this->applyStatusTab($pageQuery, $filter->deliveryStatus);

        $paginator = $pageQuery
            ->with([
                'items.product',
                'warehouse',
                'shipments' => fn ($query) => $query->latest('id'),
            ])
            ->orderByDesc('closed_at')
            ->paginate(
                perPage: $filter->perPage,
                columns: ['*'],
                pageName: 'page',
                page: $filter->page,
            )
            ->withQueryString();

        return [
            'rows' => [
                'data' => collect($paginator->items())
                    ->map(fn (Order $order) => $this->presentRow($order))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'statusTabs' => $statusTabs,
        ];
    }

    private function applyStatusTab(Builder $query, ?string $value): void
    {
        if (! $value || $value === 'all') {
            return;
        }

        if (isset(self::TAB_GROUPS[$value])) {
            $query->whereIn('delivery_status', self::TAB_GROUPS[$value]);

            return;
        }

        // Preserve direct delivery status links used elsewhere in the application.
        $query->where('delivery_status', $value);
    }

    /**
     * @return list<array{value: string, label: string, count: int}>
     */
    private function statusTabs(Builder $baseQuery, bool $hideZero = false): array
    {
        $total = (clone $baseQuery)->count();
        $tabs = [[
            'value' => 'all',
            'label' => __('operations.all'),
            'count' => $total,
        ]];

        foreach (self::TAB_GROUPS as $value => $statuses) {
            $count = (clone $baseQuery)
                ->whereIn('delivery_status', $statuses)
                ->count();

            if ($hideZero && $count === 0) {
                continue;
            }

            $tabs[] = [
                'value' => $value,
                'label' => __('operations.warehouse_tabs.'.$value),
                'count' => $count,
            ];
        }

        return $tabs;
    }

    /** @return array<string, mixed> */
    public function presentRow(Order $order): array
    {
        $shipment = $order->shipments->first();
        $provider = $order->shipping_provider ?? $shipment?->provider;
        $stockWarnings = $this->inventory->checkOrderStock($order);
        $hasInsufficientStock = collect($stockWarnings)->contains(fn (array $warning) => ! $warning['sufficient']);
        $actions = $this->shipmentActions->forShipment($shipment, $order);
        $isReturnFlow = $this->isReturnStatus($order);

        return [
            'id' => (string) $order->id,
            'orderCode' => $order->order_code,
            'closedAt' => $order->closed_at?->toIso8601String(),
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'receiverName' => $order->receiver_name,
            'receiverPhone' => $order->receiver_phone,
            'effectiveReceiverName' => $order->effectiveReceiverName(),
            'effectiveReceiverPhone' => $order->effectiveReceiverPhone(),
            'hasDifferentReceiver' => filled($order->receiver_name) || filled($order->receiver_phone),
            'shippingAddress' => $order->effectiveShippingAddress(),
            'shippingAddressRaw' => $order->shipping_address,
            'shippingAddress2' => $order->shipping_address_2,
            'customerNote' => $order->customer_note,
            'warehouseName' => $order->warehouse?->name,
            'products' => $order->items->map(fn ($item) => [
                'productName' => $item->product_name,
                'sku' => $item->product?->sku,
                'quantity' => max(1, (int) $item->quantity),
            ])->values()->all(),
            'codAmount' => $order->amount_to_collect ?? max(0, (float) $order->total - (float) $order->deposit),
            'deliveryStatus' => DeliveryStatus::tryFrom((string) $order->delivery_status)?->label() ?? $order->delivery_status,
            'deliveryStatusValue' => $order->delivery_status,
            'shippingProvider' => $provider,
            'shippingProviderLabel' => $provider
                ? config("shipping_partners.providers.{$provider}.label", strtoupper($provider))
                : null,
            'trackingNumber' => $shipment?->tracking_number,
            'shipmentError' => $shipment?->error_message,
            'inventoryDeducted' => (bool) $order->inventory_deducted_at,
            'stockWarnings' => $stockWarnings,
            'hasInsufficientStock' => $hasInsufficientStock,
            'canCreateShipment' => $actions['canCreate'] && ! $isReturnFlow,
            'canPrintLabel' => $actions['canPrintLabel'],
            'isReturnFlow' => $isReturnFlow,
            'returnReason' => $order->return_reason,
            'returnRestockedAt' => $order->return_restocked_at?->toIso8601String(),
            'canReceiveReturn' => $this->isReturnStatus($order) && ! $order->return_restocked_at,
        ];
    }

    private function isReturnStatus(Order $order): bool
    {
        return in_array((string) $order->delivery_status, self::TAB_GROUPS['returns'], true);
    }
}
