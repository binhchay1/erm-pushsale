<?php

namespace App\Services\Shipping\Carriers\Jnt;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use RuntimeException;

class JntCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly JntApiClient $client,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string
    {
        return 'jnt';
    }

    public function label(): string
    {
        return config('shipping_partners.providers.jnt.label', 'J&T Express');
    }

    public function isReady(): bool
    {
        return $this->credentials->isReady('jnt');
    }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException('J&T chưa bật hoặc thiếu API key/secret.');
        }

        $order->loadMissing(['items', 'warehouse']);
        $payload = $this->buildCreatePayload($order);

        $shipment = $this->pendingShipment($order);
        $shipment->update(['request_payload' => $payload]);

        $response = $this->client->signedRequest('/order/create', $payload, 'create_order', $order->id);

        if (! $response['success']) {
            $this->markFailed($shipment, $response['message'] ?? 'J&T từ chối tạo đơn.', $response['raw'] ?? null);
        }

        $data = is_array($response['data']) ? $response['data'] : [];

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => $order->order_code,
            'tracking_number' => (string) ($data['billcode'] ?? $data['mailno'] ?? ''),
            'status_text' => 'Đã tạo trên J&T',
            'response_payload' => $data,
        ], DeliveryStatus::PickingUp);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        throw new RuntimeException('J&T: đồng bộ trạng thái qua webhook hoặc API tracking — đang dùng webhook SaleOps.');
    }

    public function calculateFee(Order $order): array
    {
        return [
            'success' => false,
            'message' => 'J&T: tính phí qua portal hoặc bổ sung API fee sau.',
        ];
    }

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment
    {
        throw new RuntimeException('J&T: hủy đơn qua API riêng — liên hệ vận hành để bật endpoint.');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        return ['success' => false, 'message' => 'J&T: in nhãn qua portal.'];
    }

    public function testActions(): array
    {
        return ['connection' => 'Kiểm tra credentials'];
    }

    public function runTest(string $action): array
    {
        return match ($action) {
            'connection' => $this->client->testConnection(),
            default => throw new RuntimeException("Action J&T [{$action}] không hỗ trợ."),
        };
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'jnt');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        return [
            'txlogisticid' => $order->order_code,
            'ordertype' => 1,
            'sender' => [
                'name' => $pickup['pick_name'],
                'mobile' => $pickup['pick_tel'],
                'prov' => $pickup['pick_province'],
                'city' => $pickup['pick_district'],
                'area' => $pickup['pick_ward'] ?? '',
                'address' => $pickup['pick_address'],
            ],
            'receiver' => [
                'name' => $order->customer_name,
                'mobile' => $order->customer_phone,
                'prov' => $delivery['province'],
                'city' => $delivery['district'],
                'area' => $delivery['ward'],
                'address' => $delivery['address'],
            ],
            'goodsvalue' => ShippingAddressHelper::declaredValue($order),
            'itemsvalue' => ShippingAddressHelper::codAmount($order),
            'weight' => ShippingAddressHelper::totalWeightGrams($order) / 1000,
            'remark' => $order->shipping_notes ?: $order->customer_note,
        ];
    }
}
