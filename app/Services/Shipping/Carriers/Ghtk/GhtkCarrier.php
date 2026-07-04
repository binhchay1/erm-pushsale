<?php

namespace App\Services\Shipping\Carriers\Ghtk;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use Illuminate\Support\Arr;
use RuntimeException;

class GhtkCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly GhtkApiClient $client,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string
    {
        return 'ghtk';
    }

    public function label(): string
    {
        return config('shipping_partners.providers.ghtk.label', 'GHTK');
    }

    public function isReady(): bool
    {
        return $this->credentials->isReady('ghtk');
    }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException(__('carriers.not_configured', ['provider' => 'GHTK']));
        }

        $order->loadMissing(['items', 'warehouse']);
        $payload = $this->buildCreatePayload($order);

        $shipment = $this->pendingShipment($order);
        $shipment->update([
            'request_payload' => $payload,
            'transport' => $payload['order']['transport'] ?? 'road',
        ]);

        $response = $this->client->createOrder($payload, $order->id);

        if (! $response['success']) {
            $error = $response['message'] ?? __('carriers.create_rejected', ['provider' => 'GHTK']);
            $errorPayload = $response['order'] ?? Arr::get($response, 'data.error');
            if (is_array($errorPayload) && isset($errorPayload['ghtk_label'])) {
                return $this->finalizeSuccess($shipment, $order, $errorPayload);
            }

            $this->markFailed($shipment, $error, is_array($response) ? $response : null);
        }

        return $this->finalizeSuccess($shipment, $order, $response['order'] ?? $response['data'] ?? []);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);
        $trackingKey = $shipment->tracking_number ?: ('partner_id:'.$shipment->partner_order_id);
        $response = $this->client->getOrderStatus($trackingKey, $order->id);

        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? __('carriers.status_failed', ['provider' => 'GHTK']));
        }

        $data = is_array($response['data']) ? $response['data'] : [];
        $statusId = (int) ($data['status_id'] ?? $data['status'] ?? $shipment->status_id);
        $statusText = (string) ($data['status_text'] ?? $data['status_name'] ?? $shipment->status_text);
        $mapped = GhtkStatusMapper::fromStatusId($statusId ?: null, $statusText);

        $shipment->update([
            'status_id' => $statusId ?: $shipment->status_id,
            'status_text' => $mapped['label'],
            'response_payload' => $data,
            'last_synced_at' => now(),
        ]);

        if ($mapped['status']) {
            $order->update(['delivery_status' => $mapped['status']->value]);
        }

        return $shipment->fresh();
    }

    public function calculateFee(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'ghtk');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        return $this->client->calculateFee([
            'pick_address_id' => $pickup['pick_address_id'],
            'pick_province' => $pickup['pick_province'],
            'pick_district' => $pickup['pick_district'],
            'pick_ward' => $pickup['pick_ward'],
            'province' => $delivery['province'],
            'district' => $delivery['district'],
            'ward' => $delivery['ward'],
            'address' => $delivery['address'],
            'weight' => ShippingAddressHelper::totalWeightGrams($order),
            'value' => ShippingAddressHelper::declaredValue($order),
            'transport' => $order->shipping_method === 'fly' ? 'fly' : 'road',
        ], $order->id);
    }

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);
        $trackingKey = $shipment->tracking_number ?: ('partner_id:'.$shipment->partner_order_id);
        $response = $this->client->cancelOrder($trackingKey, $order->id);

        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? __('carriers.cancel_failed', ['provider' => 'GHTK']));
        }

        return $this->markCancelled($shipment, $order, __('carriers.cancelled_status', ['provider' => 'GHTK']));
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        $shipment ??= $this->requireShipment($order);

        if (! $shipment->tracking_number) {
            throw new RuntimeException(__('carriers.no_waybill', ['provider' => 'GHTK']));
        }

        return $this->client->printLabel($shipment->tracking_number, [
            'original' => 'portrait',
            'page_size' => 'A6',
        ], $order->id);
    }

    public function testActions(): array
    {
        return [
            'authenticate' => 'Kiểm tra Token',
            'pick-addresses' => 'Danh sách kho lấy hàng',
            'products' => 'Danh sách sản phẩm',
            'solutions' => 'Giải pháp Gam',
            'fee' => 'Tính phí mẫu',
        ];
    }

    public function runTest(string $action): array
    {
        return match ($action) {
            'authenticate' => $this->client->authenticate(),
            'pick-addresses' => $this->client->listPickAddresses(),
            'products' => $this->client->listProducts(),
            'solutions' => $this->client->listSolutions(),
            'fee' => $this->client->calculateFee([
                'pick_province' => config('shipping_partners.pickup.province', 'Hà Nội'),
                'pick_district' => config('shipping_partners.pickup.district', 'Quận Hoàn Kiếm'),
                'province' => 'Hồ Chí Minh',
                'district' => 'Quận 1',
                'address' => '123 Lê Lợi',
                'weight' => 500,
                'value' => 1000000,
                'transport' => 'road',
            ]),
            default => throw new RuntimeException(__('carriers.action_unsupported', ['provider' => 'GHTK', 'action' => $action])),
        };
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'ghtk');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);
        $transport = $order->shipping_method === 'fly' ? 'fly' : 'road';

        $products = $order->items->isNotEmpty()
            ? $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'weight' => max(0.1, 0.2 * max(1, (int) $item->quantity)),
                'quantity' => max(1, (int) $item->quantity),
                'price' => max(0, (int) $item->unit_price),
            ])->values()->all()
            : [[
                'name' => $order->product?->name ?? 'Sản phẩm SaleOps',
                'weight' => 0.5,
                'quantity' => 1,
                'price' => ShippingAddressHelper::declaredValue($order),
            ]];

        return [
            'products' => $products,
            'order' => array_filter([
                'id' => $order->order_code,
                'pick_name' => $pickup['pick_name'],
                'pick_address' => $pickup['pick_address'],
                'pick_province' => $pickup['pick_province'],
                'pick_district' => $pickup['pick_district'],
                'pick_ward' => $pickup['pick_ward'],
                'pick_tel' => $pickup['pick_tel'],
                'pick_address_id' => $pickup['pick_address_id'],
                'name' => $order->effectiveReceiverName(),
                'address' => $delivery['address'],
                'province' => $delivery['province'],
                'district' => $delivery['district'],
                'ward' => $delivery['ward'],
                'hamlet' => $delivery['hamlet'],
                'tel' => $order->effectiveReceiverPhone(),
                'pick_money' => ShippingAddressHelper::codAmount($order),
                'value' => ShippingAddressHelper::declaredValue($order),
                'is_freeship' => $order->shipping_fee_collected > 0 ? 0 : 1,
                'transport' => $transport,
                'note' => $order->shipping_notes ?: $order->customer_note,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /** @param  array<string, mixed>  $orderData */
    private function finalizeSuccess(Shipment $shipment, Order $order, array $orderData): Shipment
    {
        $label = (string) ($orderData['label'] ?? $orderData['ghtk_label'] ?? '');
        $statusId = (int) ($orderData['status_id'] ?? 2);
        $mapped = GhtkStatusMapper::fromStatusId($statusId ?: null);

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => (string) ($orderData['partner_id'] ?? $order->order_code),
            'tracking_number' => $label ?: $shipment->tracking_number,
            'tracking_id' => (int) ($orderData['tracking_id'] ?? 0) ?: $shipment->tracking_id,
            'fee' => (int) ($orderData['fee'] ?? 0),
            'insurance_fee' => (int) ($orderData['insurance_fee'] ?? 0),
            'status_id' => $statusId ?: null,
            'status_text' => $mapped['label'],
            'response_payload' => $orderData,
        ], $mapped['status'] ?? DeliveryStatus::PickingUp);
    }
}
