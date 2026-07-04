<?php

namespace App\Services\Shipping\Carriers\ViettelPost;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use RuntimeException;

class ViettelPostCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly ViettelPostApiClient $client,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string
    {
        return 'viettel_post';
    }

    public function label(): string
    {
        return config('shipping_partners.providers.viettel_post.label', 'Viettel Post');
    }

    public function isReady(): bool
    {
        return $this->credentials->isReady('viettel_post');
    }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException('Viettel Post chưa bật hoặc thiếu Token.');
        }

        $order->loadMissing(['items', 'warehouse']);
        $payload = $this->buildCreatePayload($order);

        $shipment = $this->pendingShipment($order);
        $shipment->update(['request_payload' => $payload]);

        $response = $this->client->createOrder($payload, $order->id);

        if (! $response['success']) {
            $this->markFailed($shipment, $response['message'] ?? 'VTP từ chối tạo đơn.', $response['raw'] ?? null);
        }

        $data = is_array($response['data']) ? $response['data'] : [];

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => $order->order_code,
            'tracking_number' => (string) ($data['ORDER_NUMBER'] ?? $data['order_number'] ?? ''),
            'fee' => (int) ($data['MONEY_TOTAL'] ?? 0),
            'status_text' => 'Đã tạo trên Viettel Post',
            'response_payload' => $data,
        ], DeliveryStatus::PickingUp);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);
        $code = $shipment->tracking_number ?: $shipment->partner_order_id;
        $response = $this->client->getOrder($code, $order->id);

        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? 'Không lấy được trạng thái VTP.');
        }

        $data = is_array($response['data']) ? $response['data'] : [];
        $statusName = (string) ($data['ORDER_STATUS'] ?? $data['STATUS_NAME'] ?? '');
        $status = ViettelPostStatusMapper::fromText($statusName);

        $shipment->update([
            'status_text' => $statusName ?: $shipment->status_text,
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
        $code = $shipment->tracking_number ?: $shipment->partner_order_id;
        $response = $this->client->cancelOrder($code, $order->id);

        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? 'Không hủy được đơn VTP.');
        }

        return $this->markCancelled($shipment, $order, 'Đã hủy trên Viettel Post');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        return [
            'success' => false,
            'message' => 'Viettel Post: in nhãn qua portal hoặc API riêng — chưa hỗ trợ PDF trực tiếp.',
        ];
    }

    public function testActions(): array
    {
        return [
            'login' => 'Đăng nhập / kiểm tra token',
            'fee' => 'Tính phí mẫu',
        ];
    }

    public function runTest(string $action): array
    {
        return match ($action) {
            'login' => $this->client->login(),
            'fee' => $this->calculateFee(new Order),
            default => throw new RuntimeException("Action VTP [{$action}] không hỗ trợ."),
        };
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'viettel_post');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);
        $creds = $this->credentials->credentials('viettel_post')['credentials'];
        $service = $order->shipping_method ?: 'VTK';

        return [
            'ORDER_NUMBER' => $order->order_code,
            'SENDER_FULLNAME' => $pickup['pick_name'],
            'SENDER_PHONE' => $pickup['pick_tel'],
            'SENDER_ADDRESS' => $pickup['pick_address'],
            'SENDER_WARD' => $pickup['pick_ward'] ?? '',
            'SENDER_DISTRICT' => $pickup['pick_district'],
            'SENDER_PROVINCE' => $pickup['pick_province'],
            'RECEIVER_FULLNAME' => $order->effectiveReceiverName(),
            'RECEIVER_PHONE' => $order->effectiveReceiverPhone(),
            'RECEIVER_ADDRESS' => $delivery['address'],
            'RECEIVER_WARD' => $delivery['ward'],
            'RECEIVER_DISTRICT' => $delivery['district'],
            'RECEIVER_PROVINCE' => $delivery['province'],
            'PRODUCT_NAME' => $order->items->pluck('product_name')->join(', ') ?: 'Hàng SaleOps',
            'PRODUCT_QUANTITY' => max(1, $order->items->sum('quantity')),
            'PRODUCT_PRICE' => ShippingAddressHelper::declaredValue($order),
            'PRODUCT_WEIGHT' => ShippingAddressHelper::totalWeightGrams($order),
            'ORDER_PAYMENT' => ShippingAddressHelper::codAmount($order) > 0 ? 2 : 1,
            'ORDER_SERVICE' => $service,
            'ORDER_NOTE' => $order->shipping_notes ?: $order->customer_note,
            'MONEY_COLLECTION' => ShippingAddressHelper::codAmount($order),
            'GROUPADDRESS_ID' => $creds['customer_code'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function buildFeePayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, 'viettel_post');
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        return [
            'SENDER_PROVINCE' => $pickup['pick_province'],
            'SENDER_DISTRICT' => $pickup['pick_district'],
            'RECEIVER_PROVINCE' => $delivery['province'],
            'RECEIVER_DISTRICT' => $delivery['district'],
            'PRODUCT_WEIGHT' => ShippingAddressHelper::totalWeightGrams($order),
            'PRODUCT_PRICE' => ShippingAddressHelper::declaredValue($order),
            'MONEY_COLLECTION' => ShippingAddressHelper::codAmount($order),
            'ORDER_SERVICE' => $order->shipping_method ?: 'VTK',
        ];
    }
}
