<?php

namespace App\Services\Operations;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Shipping\ShipmentActionResolver;
use Illuminate\Support\Collection;

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
        $all = Order::query()
            ->with(['items.product', 'warehouse', 'shipments' => fn ($q) => $q->latest('id')])
            ->whereNotNull('closed_at')
            // Tab trạng thái lọc in-memory (hỗ trợ tab gộp nhóm như "Đơn hoàn")
            ->applyReportFilter($filter->withoutDeliveryStatus())
            ->orderByDesc('closed_at')
            ->get();

        $collection = $this->filterByStatusTab($all, $filter->deliveryStatus);

        return [
            'rows' => $collection->map(fn (Order $o) => $this->presentRow($o))->values()->all(),
            'statusTabs' => $this->statusTabs($all, $filter->hideZeroStatus),
        ];
    }

    /**
     * @param  Collection<int, Order>  $all
     * @return Collection<int, Order>
     */
    private function filterByStatusTab($all, ?string $value)
    {
        if (! $value || $value === 'all') {
            return $all;
        }

        if (isset(self::TAB_GROUPS[$value])) {
            $statuses = self::TAB_GROUPS[$value];

            return $all->filter(fn (Order $o) => in_array((string) $o->delivery_status, $statuses, true));
        }

        return $all->where('delivery_status', $value);
    }

    /**
     * @param  Collection<int, Order>  $all
     * @return list<array{value: string, label: string, count: int}>
     */
    private function statusTabs($all, bool $hideZero = false): array
    {
        $tabs = [[
            'value' => 'all',
            'label' => __('operations.all'),
            'count' => $all->count(),
        ]];

        foreach (self::TAB_GROUPS as $value => $statuses) {
            $count = $all
                ->filter(fn (Order $o) => in_array((string) $o->delivery_status, $statuses, true))
                ->count();

            if ($hideZero && $count === 0) {
                continue;
            }

            $tabs[] = ['value' => $value, 'label' => __('operations.warehouse_tabs.'.$value), 'count' => $count];
        }

        return $tabs;
    }

    /** @return array<string, mixed> */
    public function presentRow(Order $order): array
    {
        $shipment = $order->shipments->first();
        $provider = $order->shipping_provider ?? $shipment?->provider;
        $stockWarnings = $this->inventory->checkOrderStock($order);
        $hasInsufficientStock = collect($stockWarnings)->contains(fn (array $w) => ! $w['sufficient']);
        $actions = $this->shipmentActions->forShipment($shipment, $order);
        $isReturnFlow = $this->isReturnStatus($order);

        return [
            'id' => (string) $order->id,
            'orderCode' => $order->order_code,
            'closedAt' => $order->closed_at?->toIso8601String(),
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'shippingAddress' => $order->shipping_address,
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
        return in_array((string) $order->delivery_status, self::TAB_GROUPS['returns']['statuses'], true);
    }
}
