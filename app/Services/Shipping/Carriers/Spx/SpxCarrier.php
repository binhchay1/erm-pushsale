<?php

namespace App\Services\Shipping\Carriers\Spx;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use RuntimeException;

class SpxCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly SpxApiClient $client,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string
    {
        return 'spx';
    }

    public function label(): string
    {
        return config('shipping_partners.providers.spx.label', 'SPX Express');
    }

    public function isReady(): bool
    {
        return $this->credentials->isReady('spx');
    }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException('SPX chưa bật hoặc thiếu thông tin cấu hình (base url, user_id, secret_key).');
        }

        $order->loadMissing(['items', 'warehouse']);
        $payload = $this->buildCreatePayload($order);

        $shipment = $this->pendingShipment($order);
        $shipment->update(['request_payload' => $payload]);

        $response = $this->client->createOrder($payload, $order->id);
        if (! ($response['success'] ?? false)) {
            $this->markFailed($shipment, $response['message'] ?? 'SPX từ chối tạo đơn.', $response['raw'] ?? null);
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => (string) ($data['partner_order_id'] ?? $data['order_code'] ?? $order->order_code),
            'tracking_number' => (string) ($data['tracking_number'] ?? $data['waybill_no'] ?? ''),
            'fee' => (int) ($data['fee'] ?? $data['shipping_fee'] ?? 0),
            'insurance_fee' => (int) ($data['insurance_fee'] ?? 0),
            'status_text' => (string) ($data['status_text'] ?? 'Đã tạo trên SPX'),
            'response_payload' => $data,
        ], DeliveryStatus::PickingUp);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);

        if (! $shipment->tracking_number && ! $shipment->partner_order_id) {
            throw new RuntimeException('Chưa có mã vận đơn/mã đối tác SPX để đồng bộ trạng thái.');
        }

        $payload = array_filter([
            'tracking_number' => $shipment->tracking_number,
            'partner_order_id' => $shipment->partner_order_id,
        ]);

        $response = $this->client->getOrderDetail($payload, $order->id);
        if (! ($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Không lấy được trạng thái SPX.');
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $rawStatus = (string) ($data['status_text'] ?? $data['status'] ?? $data['state'] ?? '');
        $status = DeliveryStatusTextMapper::map($rawStatus);

        $shipment->update([
            'status_id' => isset($data['status_id']) ? (int) $data['status_id'] : $shipment->status_id,
            'status_text' => $rawStatus !== '' ? $rawStatus : $shipment->status_text,
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
        $payload = array_filter([
            'tracking_number' => $shipment->tracking_number,
            'partner_order_id' => $shipment->partner_order_id,
        ]);

        $response = $this->client->cancelOrder($payload, $order->id);
        if (! ($response['success'] ?? false)) {
            throw new RuntimeException($response['message'] ?? 'Không hủy được đơn SPX.');
        }

        return $this->markCancelled($shipment, $order, 'Đã hủy trên SPX');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        $shipment ??= $this->requireShipment($order);

        if (! $shipment->tracking_number) {
            throw new RuntimeException('Chưa có mã vận đơn SPX.');
        }

        return $this->client->printLabel([
            'tracking_number' => $shipment->tracking_number,
            'partner_order_id' => $shipment->partner_order_id,
        ], $order->id);
    }

    public function testActions(): array
    {
        return [
            'connection' => 'Kiểm tra kết nối SPX',
            'fee' => 'Tính phí mẫu',
        ];
    }

    public function runTest(string $action): array
    {
        return match ($action) {
            'connection' => $this->client->testConnection(),
            'fee' => $this->calculateFee(new Order),
            default => throw new RuntimeException("Action SPX [{$action}] không hỗ trợ."),
        };
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'spx');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        return [
            'partner_order_id' => $order->order_code,
            'service_code' => $order->shipping_method ?: 'standard',
            'sender' => [
                'name' => $pickup['pick_name'],
                'phone' => $pickup['pick_tel'],
                'province' => $pickup['pick_province'],
                'district' => $pickup['pick_district'],
                'ward' => $pickup['pick_ward'] ?? '',
                'address' => $pickup['pick_address'],
            ],
            'receiver' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'province' => $delivery['province'],
                'district' => $delivery['district'],
                'ward' => $delivery['ward'],
                'address' => $delivery['address'],
            ],
            'parcel' => [
                'weight_grams' => ShippingAddressHelper::totalWeightGrams($order),
                'declared_value' => ShippingAddressHelper::declaredValue($order),
                'cod_amount' => ShippingAddressHelper::codAmount($order),
                'item_count' => max(1, $order->items->sum('quantity')),
                'content' => $order->items->pluck('product_name')->join(', ') ?: 'Hàng SaleOps',
            ],
            'note' => $order->shipping_notes ?: $order->customer_note,
        ];
    }

    /** @return array<string, mixed> */
    private function buildFeePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'spx');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        return [
            'from_province' => $pickup['pick_province'],
            'from_district' => $pickup['pick_district'],
            'to_province' => $delivery['province'],
            'to_district' => $delivery['district'],
            'weight_grams' => ShippingAddressHelper::totalWeightGrams($order),
            'declared_value' => ShippingAddressHelper::declaredValue($order),
            'cod_amount' => ShippingAddressHelper::codAmount($order),
            'service_code' => $order->shipping_method ?: 'standard',
        ];
    }
}
