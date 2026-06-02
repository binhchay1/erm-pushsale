<?php

namespace App\Services\Shipping\Carriers\Ghn;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use RuntimeException;

class GhnCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly GhnApiClient $client,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string
    {
        return 'ghn';
    }

    public function label(): string
    {
        return config('shipping_partners.providers.ghn.label', 'GHN');
    }

    public function isReady(): bool
    {
        return $this->credentials->isReady('ghn');
    }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException('GHN chưa bật hoặc thiếu Token/Shop ID.');
        }

        $order->loadMissing(['items', 'warehouse']);
        $payload = $this->buildCreatePayload($order);

        $shipment = $this->pendingShipment($order);
        $shipment->update(['request_payload' => $payload]);

        $response = $this->client->createOrder($payload, $order->id);

        if (! $response['success']) {
            $this->markFailed($shipment, $response['message'] ?? 'GHN từ chối tạo đơn.', $response['raw'] ?? null);
        }

        $data = is_array($response['data']) ? $response['data'] : [];

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => $order->order_code,
            'tracking_number' => (string) ($data['order_code'] ?? ''),
            'fee' => (int) ($data['total_fee'] ?? 0),
            'status_text' => 'Đã tạo trên GHN',
            'response_payload' => $data,
        ], DeliveryStatus::PickingUp);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);

        if (! $shipment->tracking_number) {
            throw new RuntimeException('Chưa có mã vận đơn GHN.');
        }

        $response = $this->client->getOrderDetail($shipment->tracking_number, $order->id);

        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? 'Không lấy được trạng thái GHN.');
        }

        $data = is_array($response['data']) ? $response['data'] : [];
        $status = GhnStatusMapper::fromText((string) ($data['status'] ?? ''));

        $shipment->update([
            'status_text' => (string) ($data['status'] ?? $shipment->status_text),
            'response_payload' => $data,
            'last_synced_at' => now(),
        ]);

        if ($status) {
            $order->update(['delivery_status' => $status->value]);
        }

        return $shipment->fresh();
    }

    public function calculateFee(Order $order): array
    {
        return $this->client->calculateFee($this->buildFeePayload($order), $order->id);
    }

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);

        $response = $this->client->cancelOrder([
            'order_codes' => [$shipment->tracking_number],
        ], $order->id);

        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? 'Không hủy được đơn GHN.');
        }

        return $this->markCancelled($shipment, $order, 'Đã hủy trên GHN');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        $shipment ??= $this->requireShipment($order);
        $result = $this->client->printLabel($shipment->tracking_number);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'GHN trả token in nhãn — mở link từ response.',
            'data' => $result['data'] ?? $result['raw'] ?? null,
        ];
    }

    public function testActions(): array
    {
        return [
            'shop' => 'Thông tin cửa hàng',
            'fee' => 'Tính phí mẫu',
        ];
    }

    public function runTest(string $action): array
    {
        return match ($action) {
            'shop' => $this->client->getStore(),
            'fee' => $this->calculateFee(new Order),
            default => throw new RuntimeException("Action GHN [{$action}] không hỗ trợ."),
        };
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'ghn');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);
        $serviceType = is_numeric($order->shipping_method) ? (int) $order->shipping_method : 2;

        return [
            'payment_type_id' => 2,
            'note' => $order->shipping_notes ?: $order->customer_note,
            'required_note' => 'CHOXEMHANGKHONGTHU',
            'from_name' => $pickup['pick_name'],
            'from_phone' => $pickup['pick_tel'],
            'from_address' => $pickup['pick_address'],
            'from_ward_name' => $pickup['pick_ward'] ?? '',
            'from_district_name' => $pickup['pick_district'],
            'from_province_name' => $pickup['pick_province'],
            'to_name' => $order->customer_name,
            'to_phone' => $order->customer_phone,
            'to_address' => $delivery['address'],
            'to_ward_name' => $delivery['ward'],
            'to_district_name' => $delivery['district'],
            'to_province_name' => $delivery['province'],
            'cod_amount' => ShippingAddressHelper::codAmount($order),
            'content' => $order->items->pluck('product_name')->join(', ') ?: 'Hàng SaleOps',
            'weight' => ShippingAddressHelper::totalWeightGrams($order),
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'service_type_id' => $serviceType,
            'client_order_code' => $order->order_code,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildFeePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'ghn');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        return [
            'from_district_id' => null,
            'from_ward_code' => null,
            'to_district_id' => null,
            'to_ward_code' => null,
            'from_district_name' => $pickup['pick_district'],
            'from_province_name' => $pickup['pick_province'],
            'to_district_name' => $delivery['district'],
            'to_province_name' => $delivery['province'],
            'weight' => ShippingAddressHelper::totalWeightGrams($order),
            'service_type_id' => 2,
            'cod_value' => ShippingAddressHelper::codAmount($order),
        ];
    }
}
