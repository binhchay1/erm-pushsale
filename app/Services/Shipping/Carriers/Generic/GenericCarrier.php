<?php

namespace App\Services\Shipping\Carriers\Generic;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingApiLog;
use App\Services\Shipping\Support\AbstractShippingCarrier;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingAddressHelper;
use App\Services\Shipping\Support\ShippingHttpSsl;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adapter cấu hình cho hãng chưa có SDK công khai hoặc cho cổng trung gian.
 * Contract request là payload chuẩn ERM; endpoint/credential được cấu hình theo từng provider.
 */
class GenericCarrier extends AbstractShippingCarrier
{
    public function __construct(
        private readonly string $providerKey,
        private readonly PartnerCredentialResolver $credentials,
    ) {}

    public function provider(): string { return $this->providerKey; }
    public function label(): string { return config("shipping_partners.providers.{$this->providerKey}.label", $this->providerKey); }
    public function isReady(): bool { return $this->credentials->isReady($this->providerKey); }

    public function createFromOrder(Order $order): Shipment
    {
        $this->assertReady();
        $order->loadMissing(['items.product', 'warehouse']);
        $payload = $this->canonicalPayload($order);
        $shipment = $this->pendingShipment($order);
        $shipment->update(['request_payload' => $payload]);

        $result = $this->request('POST', $this->path('create_path', '/shipments'), $payload, 'create_order', $order->id);
        if (! $result['success']) {
            $this->markFailed($shipment, $result['message'] ?: "{$this->label()} từ chối tạo vận đơn", $result['raw']);
        }

        $data = $this->responseData($result['raw']);
        $tracking = $this->first($data, ['tracking_number', 'tracking_code', 'waybill', 'billcode', 'label_id', 'order_code', 'code']);
        if (! filled($tracking)) {
            $this->markFailed($shipment, "{$this->label()} không trả về mã vận đơn.", $result['raw']);
        }

        $fee = $this->money($this->first($data, ['total_fee', 'shipping_fee', 'fee', 'service_fee']));
        $statusText = (string) ($this->first($data, ['status_text', 'status_name', 'status']) ?: 'Đã đăng vận đơn');

        return $this->applySuccess($shipment, $order, [
            'partner_order_id' => (string) ($this->first($data, ['partner_order_id', 'client_order_code', 'reference', 'order_id']) ?: $order->order_code),
            'tracking_number' => (string) $tracking,
            'fee' => $fee,
            'cod_amount' => (int) $order->amount_to_collect,
            'status_text' => $statusText,
            'response_payload' => $result['raw'],
            'posted_at' => now(),
        ], DeliveryStatus::Posted);
    }

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment
    {
        $this->assertReady();
        $shipment ??= $this->requireShipment($order);
        $path = $this->replaceTracking($this->path('status_path', '/shipments/{tracking}'), $shipment);
        $result = $this->request('GET', $path, null, 'order_status', $order->id);

        if (! $result['success']) {
            throw new RuntimeException($result['message'] ?: "Không thể lấy trạng thái {$this->label()}.");
        }

        $data = $this->responseData($result['raw']);
        $rawStatus = (string) ($this->first($data, ['status_text', 'status_name', 'status', 'state', 'order_status']) ?: $shipment->status_text);
        $mapped = DeliveryStatusTextMapper::map($rawStatus);
        $fee = $this->money($this->first($data, ['shipping_fee', 'total_fee', 'fee', 'service_fee']));
        $returnFee = $this->money($this->first($data, ['return_fee', 'return_shipping_fee']));
        $codFee = $this->money($this->first($data, ['cod_fee', 'collection_fee']));

        $shipment->update(array_filter([
            'status_text' => $rawStatus,
            'fee' => $fee ?: $shipment->fee,
            'return_fee' => $returnFee ?: $shipment->return_fee,
            'cod_fee' => $codFee ?: $shipment->cod_fee,
            'response_payload' => $result['raw'],
            'last_synced_at' => now(),
            'last_event_at' => now(),
        ], fn ($v) => $v !== null));

        $order->update(array_filter([
            'delivery_status' => $mapped?->value,
            'carrier_service_fee' => $fee ?: null,
            'carrier_return_fee' => $returnFee ?: null,
            'cod_fee' => $codFee ?: null,
            'last_delivery_event_at' => now(),
        ], fn ($v) => $v !== null));

        return $shipment->fresh();
    }

    public function calculateFee(Order $order): array
    {
        $this->assertReady();
        $path = $this->path('fee_path', '/rates');
        if (! filled($path)) {
            return ['success' => false, 'message' => 'Đối tác chưa cấu hình endpoint tính phí.'];
        }

        return $this->request('POST', $path, $this->canonicalPayload($order->loadMissing(['items.product', 'warehouse'])), 'calculate_fee', $order->id);
    }

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment
    {
        $this->assertReady();
        $shipment ??= $this->requireShipment($order);
        $path = $this->path('cancel_path', '');
        if (! filled($path)) {
            throw new RuntimeException('Đối tác chưa cấu hình endpoint hủy vận đơn.');
        }

        $result = $this->request('POST', $this->replaceTracking($path, $shipment), [
            'tracking_number' => $shipment->tracking_number,
            'client_order_code' => $order->order_code,
        ], 'cancel_order', $order->id);

        if (! $result['success']) {
            throw new RuntimeException($result['message'] ?: "Không thể hủy vận đơn {$this->label()}.");
        }

        return $this->markCancelled($shipment, $order, 'Đã hủy vận đơn');
    }

    public function printLabel(Order $order, ?Shipment $shipment = null): array
    {
        $shipment ??= $this->requireShipment($order);
        $path = $this->path('label_path', '');
        if (! filled($path)) {
            return ['success' => false, 'message' => 'Đối tác chưa cấu hình endpoint in nhãn.'];
        }

        $result = $this->request('GET', $this->replaceTracking($path, $shipment), null, 'print_label', $order->id, false);
        $raw = $result['raw'];
        $url = is_array($raw) ? $this->first($this->responseData($raw), ['label_url', 'print_url', 'url']) : null;

        return $url
            ? ['success' => true, 'message' => (string) $url, 'data' => ['url' => $url]]
            : ['success' => $result['success'], 'message' => $result['message'] ?: 'Đã nhận dữ liệu nhãn.', 'data' => $raw];
    }

    public function testActions(): array
    {
        return ['connection' => 'Kiểm tra kết nối'];
    }

    public function runTest(string $action): array
    {
        if ($action !== 'connection') {
            throw new RuntimeException("Action {$action} không được hỗ trợ.");
        }

        $this->assertReady();
        $path = $this->path('status_path', '/');
        $path = str_contains($path, '{tracking}') ? '/' : $path;

        return $this->request('GET', $path, null, 'test_connection');
    }

    /** @return array<string, mixed> */
    private function canonicalPayload(Order $order): array
    {
        $pickup = ShippingAddressHelper::pickupForOrder($order, $this->credentials, $this->providerKey);
        $delivery = ShippingAddressHelper::deliveryForOrder($order);
        $settings = $this->credentials->settings($this->providerKey);
        $creds = $this->credentials->credentials($this->providerKey)['credentials'];

        return [
            'provider' => $creds['provider_code'] ?? $this->providerKey,
            'account_id' => $creds['account_id'] ?? null,
            'client_order_code' => $order->order_code,
            'reference' => (string) $order->id,
            'service_code' => $order->shipping_method ?: 'standard',
            'pickup' => [
                'name' => $pickup['pick_name'], 'phone' => $pickup['pick_tel'], 'address' => $pickup['pick_address'],
                'ward' => $pickup['pick_ward'] ?? null, 'district' => $pickup['pick_district'], 'province' => $pickup['pick_province'],
            ],
            'recipient' => [
                'name' => $order->effectiveReceiverName(), 'phone' => $order->effectiveReceiverPhone(),
                'address' => $delivery['address'], 'ward' => $delivery['ward'],
                'district' => $delivery['district'], 'province' => $delivery['province'],
            ],
            'parcel' => [
                'weight_grams' => ShippingAddressHelper::totalWeightGrams($order),
                'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10,
                'goods_type' => $settings['goods_type'] ?? 'parcel',
                'declared_value' => (int) $order->effectiveRevenue(),
            ],
            'cod' => [
                'amount' => (int) $order->amount_to_collect,
                'enabled' => (bool) ($settings['use_carrier_cod'] ?? true),
            ],
            'options' => [
                'pickup_mode' => $settings['pickup_mode'] ?? 'carrier_pickup',
                'inspection_mode' => $settings['inspection_mode'] ?? 'view_only',
                'insurance_enabled' => (bool) ($settings['insurance_enabled'] ?? false),
                'allow_partial_delivery' => (bool) ($settings['allow_partial_delivery'] ?? false),
                'extra_services' => $settings['extra_services'] ?? [],
            ],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->product?->sku,
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
                'weight_grams' => (int) ($item->product?->weight_grams ?: 0),
                'origin' => $item->origin,
            ])->values()->all(),
            'note' => $order->shipping_notes ?: $order->customer_note,
            'callback_url' => url("/api/v1/shipping/webhooks/{$this->providerKey}"),
        ];
    }

    /** @return array{success: bool, raw: mixed, message: ?string, http_status: int} */
    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        string $action = 'request',
        ?int $orderId = null,
        bool $expectJson = true,
    ): array {
        $pack = $this->credentials->credentials($this->providerKey);
        $creds = $pack['credentials'];
        $baseUrl = rtrim((string) $pack['base_url'], '/');
        if ($baseUrl === '') {
            throw new RuntimeException("{$this->label()} chưa cấu hình API Base URL.");
        }

        $headerName = (string) ($creds['auth_header'] ?? 'Authorization');
        $prefix = trim((string) ($creds['auth_prefix'] ?? 'Bearer'));
        $token = (string) ($creds['api_token'] ?? '');
        $authValue = trim($prefix.' '.$token);
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($token !== '') {
            $headers[$headerName] = $authValue;
        }
        if (filled($creds['account_id'] ?? null)) {
            $headers['X-Account-Id'] = (string) $creds['account_id'];
        }

        $url = $baseUrl.'/'.ltrim($path, '/');
        $pending = Http::timeout(45)->withHeaders($headers)->withOptions(['verify' => ShippingHttpSsl::verifyOption()]);

        /** @var Response $response */
        $response = match (strtoupper($method)) {
            'GET' => $pending->get($url, $payload ?? []),
            'POST' => $pending->asJson()->post($url, $payload ?? []),
            'PUT' => $pending->asJson()->put($url, $payload ?? []),
            default => throw new RuntimeException("HTTP method {$method} không hỗ trợ."),
        };

        $raw = $expectJson ? ($response->json() ?? ['raw' => $response->body()]) : ($response->json() ?? $response->body());
        $message = is_array($raw) ? ($raw['message'] ?? $raw['error'] ?? null) : null;
        $success = $response->successful() && (! is_array($raw) || ($raw['success'] ?? true) !== false);

        ShippingApiLog::query()->create([
            'provider' => $this->providerKey,
            'order_id' => $orderId,
            'action' => $action,
            'method' => strtoupper($method),
            'endpoint' => $path,
            'http_status' => $response->status(),
            'success' => $success,
            'message' => is_scalar($message) ? (string) $message : null,
            'request_payload' => $payload,
            'response_payload' => is_array($raw) ? $raw : ['raw' => $raw],
        ]);

        return [
            'success' => $success,
            'raw' => $raw,
            'message' => is_scalar($message) ? (string) $message : null,
            'http_status' => $response->status(),
        ];
    }

    private function path(string $key, string $fallback): string
    {
        $creds = $this->credentials->credentials($this->providerKey)['credentials'];
        return (string) ($creds[$key] ?? $fallback);
    }

    private function replaceTracking(string $path, Shipment $shipment): string
    {
        return str_replace(
            ['{tracking}', '{order_code}', '{partner_order_id}'],
            [urlencode((string) $shipment->tracking_number), urlencode((string) $shipment->order?->order_code), urlencode((string) $shipment->partner_order_id)],
            $path,
        );
    }

    /** @return array<string, mixed> */
    private function responseData(mixed $raw): array
    {
        if (! is_array($raw)) return [];
        $data = $raw['data'] ?? $raw['shipment'] ?? $raw['order'] ?? $raw;
        return is_array($data) ? $data : [];
    }

    private function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);
            if (filled($value) && is_scalar($value)) return $value;
        }
        return null;
    }

    private function money(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_int($value)) return max(0, $value);
        if (is_float($value)) return max(0, (int) round($value));
        return max(0, (int) preg_replace('/[^0-9-]/', '', (string) $value));
    }

    private function assertReady(): void
    {
        if (! $this->isReady()) {
            throw new RuntimeException("{$this->label()} chưa được bật hoặc thiếu thông tin bắt buộc.");
        }
    }
}
