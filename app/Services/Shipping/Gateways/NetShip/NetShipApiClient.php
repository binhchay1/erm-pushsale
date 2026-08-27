<?php

namespace App\Services\Shipping\Gateways\NetShip;

use App\Services\Shipping\Support\AbstractCarrierHttpClient;
use App\Services\Shipping\Support\PartnerCredentialResolver;

class NetShipApiClient extends AbstractCarrierHttpClient
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    protected function provider(): string
    {
        return 'netship';
    }

    public function cacheKeyPrefix(): string
    {
        $creds = $this->credentials->credentials('netship')['credentials'];
        $token = (string) ($creds['token'] ?? '');
        $base = $this->baseUrl();

        return substr(hash('sha256', $base.'|'.$token), 0, 16);
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $creds = $this->credentials->credentials('netship')['credentials'];

        return array_filter([
            'token' => (string) ($creds['token'] ?? ''),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, ?int $orderId = null): array
    {
        return $this->normalizeCreateResponse(
            $this->requestJson('POST', '/api/third-party/order', json: $payload, action: 'create_order', orderId: $orderId)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function estimateFee(array $payload, ?int $orderId = null): array
    {
        return $this->requestJson('POST', '/api/third-party/order/estimate-fee', json: $payload, action: 'estimate_fee', orderId: $orderId);
    }

    /** @return array<string, mixed> */
    public function cancelOrder(int|string $netshipOrderId, ?int $orderId = null): array
    {
        return $this->requestJson(
            'POST',
            '/api/third-party/order/cancel/'.rawurlencode((string) $netshipOrderId),
            action: 'cancel_order',
            orderId: $orderId,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function queryOrders(array $query = [], ?int $orderId = null): array
    {
        return $this->requestJson('GET', '/api/third-party/order', query: $query, action: 'query_orders', orderId: $orderId);
    }

    /** @return list<array<string, mixed>> */
    public function listProvinces(): array
    {
        return $this->listFrom('/api/address/provinces', 'list_provinces');
    }

    /** @return list<array<string, mixed>> */
    public function listDistricts(int $provinceId): array
    {
        return $this->listFrom('/api/address/districts', 'list_districts', ['provinceId' => $provinceId]);
    }

    /** @return list<array<string, mixed>> */
    public function listWards(int $districtId): array
    {
        return $this->listFrom('/api/address/ward', 'list_wards', ['districtId' => $districtId]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function listFrom(string $path, string $action, array $query = []): array
    {
        $response = $this->requestJson('GET', $path, query: $query ?: null, action: $action);
        $data = $response['data'] ?? $response['raw'] ?? [];
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }
        if (is_array($data)) {
            foreach (['items', 'content', 'data', 'result'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                    return $data[$key];
                }
            }
        }

        return [];
    }

    /**
     * NetShip docs không chuẩn hóa response create — chấp nhận id ở nhiều vị trí.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeCreateResponse(array $response): array
    {
        $raw = is_array($response['raw'] ?? null) ? $response['raw'] : [];
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $merged = array_replace_recursive($raw, $data);

        $id = $merged['id']
            ?? $merged['orderId']
            ?? $merged['order_id']
            ?? data_get($merged, 'data.id')
            ?? data_get($merged, 'order.id');

        if ($id !== null && $id !== '') {
            $response['success'] = $response['success'] && true;
            $response['data'] = array_merge(is_array($response['data'] ?? null) ? $response['data'] : [], [
                'id' => $id,
                'tracking_number' => $merged['trackingNumber']
                    ?? $merged['tracking_number']
                    ?? $merged['billCode']
                    ?? $merged['code']
                    ?? (string) $id,
            ]);
        } elseif (($response['http_status'] ?? 0) >= 200 && ($response['http_status'] ?? 0) < 300 && $id === null) {
            // Một số API trả 200 nhưng body lỗi — giữ success theo client.
        }

        return $response;
    }
}
