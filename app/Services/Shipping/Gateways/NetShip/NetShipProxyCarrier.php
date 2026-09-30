<?php

namespace App\Services\Shipping\Gateways\NetShip;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use RuntimeException;

/**
 * Proxy: ĐVVC trên đơn vẫn là VTP/GHTK… nhưng HTTP đi qua NetShip.
 */
class NetShipProxyCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly string $businessProvider,
        private readonly string $netshipCarrierCode,
        private readonly NetShipApiClient $client,
        private readonly NetShipAddressResolver $addresses,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string
    {
        return $this->businessProvider;
    }

    public function label(): string
    {
        $base = config("shipping_partners.providers.{$this->businessProvider}.label", $this->businessProvider);

        return $base.' (qua NetShip)';
    }

    public function isReady(): bool
    {
        return $this->credentials->isReady('netship');
    }

    public function createFromOrder(Order $order): Shipment
    {
        if (! $this->isReady()) {
            throw new RuntimeException(__('messages.shipping_actions.netship_not_ready'));
        }

        $order->loadMissing(['items', 'warehouse']);
        $payload = $this->buildPayload($order);
        $shipment = $this->pendingShipment($order);
        $shipment->update([
            'request_payload' => array_merge($payload, [
                'gateway' => 'netship',
                'netship_carrier_code' => $this->netshipCarrierCode,
            ]),
        ]);

        $response = $this->client->createOrder($payload, $order->id);
        if (! $response['success']) {
            $this->markFailed($shipment, $response['message'] ?? __('messages.shipping_actions.netship_create_failed'), $response['raw'] ?? null);
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $netshipId = (string) ($data['id'] ?? '');
        $external = trim((string) ($data['external_code'] ?? ''));
        $linkId = trim((string) ($data['link_id'] ?? ''));
        // Cột "Mã đơn" của file đối soát NetShip là externalCode, không phải id số.
        $tracking = $external !== '' ? $external : (string) ($data['tracking_number'] ?? $netshipId);

        if ($netshipId === '' && $tracking === '') {
            $this->markFailed($shipment, __('messages.shipping_actions.netship_create_failed'), $response['raw'] ?? null);
        }

        $result = $this->applySuccess($shipment, $order, [
            'partner_order_id' => $this->customerCode($order),
            'tracking_number' => $tracking !== '' ? $tracking : $netshipId,
            // tracking_id cột bigint — chỉ lưu khi NetShip trả id số.
            'tracking_id' => ctype_digit($netshipId) ? (int) $netshipId : null,
            'fee' => (int) ($data['fee'] ?? $data['shippingFee'] ?? 0),
            'status_text' => 'Đã tạo qua NetShip',
            'response_payload' => array_merge($data, [
                'gateway' => 'netship',
                'netship_order_id' => $netshipId,
                'netship_link_id' => $linkId,
                'netship_external_code' => $external,
                'netship_carrier_code' => $this->netshipCarrierCode,
                'business_provider' => $this->businessProvider,
                'partner_refs' => array_values(array_unique(array_filter(array_merge(
                    NetShipPartnerRefs::collect([
                        'raw' => $response['raw'] ?? null,
                        'data' => $data,
                    ]),
                    [$this->customerCode($order), $tracking, $netshipId],
                )))),
            ]),
        ], DeliveryStatus::PickingUp);

        app(ShippingGatewayTraceRecorder::class)->attachShipment($order->id, $result->id, $external);

        ShippingPartnerConnection::forProvider('netship')->update(['last_synced_at' => now()]);

        return $result;
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);
        $netshipId = $this->netshipIdFrom($shipment);

        $match = null;
        foreach ($this->searchKeys($shipment) as $search) {
            $response = $this->client->queryOrders(['search' => $search], $order->id);
            if (! $response['success']) {
                throw new RuntimeException($response['message'] ?? __('messages.shipping_actions.netship_sync_failed'));
            }

            $match = $this->matchOrderRow($this->extractOrderRows($response), $netshipId, $order);
            if ($match !== null) {
                break;
            }
        }

        if (! is_array($match)) {
            throw new RuntimeException(__('messages.shipping_actions.netship_sync_failed'));
        }

        $statusId = $match['status'] ?? $match['statusId'] ?? null;
        $mapped = NetShipStatusMapper::fromStatusId($statusId);
        $existingPayload = is_array($shipment->response_payload) ? $shipment->response_payload : [];
        $shipment->update([
            'status_text' => $mapped['label'],
            'status_id' => is_numeric($statusId) ? (int) $statusId : $shipment->status_id,
            'response_payload' => array_merge(
                $existingPayload,
                [
                    'gateway' => 'netship',
                    'last_query' => $match,
                    'partner_refs' => array_values(array_unique(array_merge(
                        is_array($existingPayload['partner_refs'] ?? null) ? $existingPayload['partner_refs'] : [],
                        NetShipPartnerRefs::collect($match),
                    ))),
                ],
            ),
            'last_synced_at' => now(),
        ]);
        if ($mapped['status']) {
            $order->update(['delivery_status' => $mapped['status']->value]);
        }

        return $shipment->fresh();
    }

    public function calculateFee(Order $order): array
    {
        $order->loadMissing(['items', 'warehouse']);
        $response = $this->client->estimateFee($this->buildPayload($order), $order->id);

        return [
            'success' => $response['success'],
            'message' => $response['message'],
            'fee' => (int) (data_get($response, 'data.fee')
                ?? data_get($response, 'data.shippingFee')
                ?? data_get($response, 'raw.fee')
                ?? 0),
            'raw' => $response['raw'] ?? $response['data'],
            'gateway' => 'netship',
        ];
    }

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->requireShipment($order);
        $netshipId = $this->netshipIdFrom($shipment);
        if ($netshipId === '') {
            throw new RuntimeException(__('messages.shipping_actions.netship_missing_id'));
        }

        $response = $this->client->cancelOrder($netshipId, $order->id);
        if (! $response['success']) {
            throw new RuntimeException($response['message'] ?? __('messages.shipping_actions.netship_cancel_failed'));
        }

        return $this->markCancelled($shipment, $order, 'Đã hủy trên NetShip');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        return [
            'success' => false,
            'message' => __('messages.shipping_actions.netship_label_unsupported'),
        ];
    }

    public function testActions(): array
    {
        return [
            'connection' => 'Kiểm tra token / địa chỉ',
            'provinces' => 'Tải danh sách tỉnh NetShip',
        ];
    }

    public function runTest(string $action): array
    {
        return match ($action) {
            'connection', 'provinces' => [
                'success' => $this->isReady(),
                'message' => $this->isReady()
                    ? 'NetShip sẵn sàng ('.count($this->addresses->provinces()).' tỉnh).'
                    : 'NetShip chưa bật hoặc thiếu token.',
                'provinces_sample' => array_slice($this->addresses->provinces(), 0, 5),
            ],
            default => throw new RuntimeException("Action NetShip [{$action}] không hỗ trợ."),
        };
    }

    /** @return array<string, mixed> */
    private function buildPayload(Order $order): array
    {
        $creds = $this->credentials->credentials('netship')['credentials'];
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, $this->businessProvider);
        $delivery = ShippingAddressHelper::deliveryForOrder($order);

        $senderIds = $this->addresses->resolve(
            (string) $pickup['pick_province'],
            (string) $pickup['pick_district'],
            (string) ($pickup['pick_ward'] ?: config('shipping_partners.default_geo.ward')),
        );
        $receiverIds = $this->receiverIds($order, $delivery);

        $productName = $order->items
            ->map(fn ($item) => trim((string) $item->product_name))
            ->filter()
            ->take(3)
            ->implode(' + ');
        if ($productName === '') {
            $productName = 'Hang hoa';
        }

        return [
            'customerCode' => $this->customerCode($order),
            'senderName' => $pickup['pick_name'],
            'senderPhone' => $pickup['pick_tel'],
            'senderAddress' => $pickup['pick_address'],
            'senderProvinceId' => $senderIds['provinceId'],
            'senderDistrictId' => $senderIds['districtId'],
            'senderWardId' => $senderIds['wardId'],
            'receiverName' => $order->effectiveReceiverName(),
            'receiverPhone' => $order->effectiveReceiverPhone() ?: $order->customer_phone,
            'receiverAddress' => $delivery['address'],
            'receiverProvinceId' => $receiverIds['provinceId'],
            'receiverDistrictId' => $receiverIds['districtId'],
            'receiverWardId' => $receiverIds['wardId'],
            'productName' => mb_substr($productName, 0, 200),
            'quantity' => max(1, (int) $order->items->sum('quantity')),
            'productType' => (string) ($creds['product_type'] ?? 'Sức khỏe'),
            'codPrice' => ShippingAddressHelper::codAmount($order),
            'price' => ShippingAddressHelper::declaredValue($order),
            'weight' => ShippingAddressHelper::totalWeightGrams($order),
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'orderNote' => (string) ($order->shipping_notes ?: $order->customer_note ?: ''),
            'deliveryNote' => (int) ($creds['delivery_note'] ?? 1),
            'receiverPay' => false,
            'pickupType' => (int) ($creds['pickup_type'] ?? 0),
        ];
    }

    private function customerCode(Order $order): string
    {
        return filled($order->order_code) ? (string) $order->order_code : 'O-'.$order->id;
    }

    private function netshipIdFrom(Shipment $shipment): string
    {
        $payload = is_array($shipment->response_payload) ? $shipment->response_payload : [];

        return (string) ($payload['netship_order_id']
            ?? $payload['id']
            ?? $shipment->tracking_id
            ?? '');
    }

    /**
     * `shipping_geo` giữ sẵn mã hành chính GSO (`province_code`/`district_code`/`ward_code`),
     * đúng bộ mã NetShip dùng. Ưu tiên mã để khỏi dò theo tên — tên trùng nhau
     * (vd. "Phường 15") có thể khớp nhầm phường của quận khác.
     *
     * @param  array{province: string, district: string, ward: string, hamlet: string, address: string}  $delivery
     * @return array{provinceId: int, districtId: int, wardId: int}
     */
    private function receiverIds(Order $order, array $delivery): array
    {
        $geo = is_array($order->shipping_geo) ? $order->shipping_geo : [];
        $province = (int) ($geo['province_code'] ?? 0);
        $district = (int) ($geo['district_code'] ?? 0);
        $ward = (int) ($geo['ward_code'] ?? 0);

        if (($geo['mode'] ?? 'old') === 'old' && $province > 0 && $district > 0 && $ward > 0) {
            return ['provinceId' => $province, 'districtId' => $district, 'wardId' => $ward];
        }

        return $this->addresses->resolve(
            $delivery['province'],
            $delivery['district'] ?: $delivery['ward'],
            $delivery['ward'] ?: config('shipping_partners.default_geo.ward'),
        );
    }

    /**
     * `search` on NetShip query_orders only matches externalCode and linkId —
     * customerCode and the NetShip id are not searchable.
     *
     * @return list<string>
     */
    private function searchKeys(Shipment $shipment): array
    {
        $payload = is_array($shipment->response_payload) ? $shipment->response_payload : [];

        return array_values(array_unique(array_filter([
            (string) ($payload['netship_external_code'] ?? ''),
            (string) ($payload['netship_link_id'] ?? ''),
            (string) $shipment->tracking_number,
        ], fn (string $key): bool => $key !== '')));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function matchOrderRow(array $rows, string $netshipId, Order $order): ?array
    {
        foreach ($rows as $row) {
            if ($netshipId !== '' && (string) ($row['id'] ?? '') === $netshipId) {
                return $row;
            }
            if ((string) ($row['customerCode'] ?? $row['customer_code'] ?? '') === $this->customerCode($order)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function extractOrderRows(array $response): array
    {
        $data = $response['data'] ?? $response['raw'] ?? [];
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }
        if (is_array($data)) {
            foreach (['content', 'items', 'orders', 'data'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                    return $data[$key];
                }
            }
        }

        return [];
    }
}
