<?php

namespace App\Services\Shipping\Carriers\Ghn;

use App\Services\Shipping\Support\AbstractCarrierHttpClient;
use App\Services\Shipping\Support\PartnerCredentialResolver;

class GhnApiClient extends AbstractCarrierHttpClient
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    protected function provider(): string
    {
        return 'ghn';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $creds = $this->credentials->credentials('ghn')['credentials'];

        return array_filter([
            'Token' => $creds['token'] ?? null,
            'ShopId' => (string) ($creds['shop_id'] ?? ''),
            'Content-Type' => 'application/json',
        ]);
    }

    /** @return array<string, mixed> */
    public function getStore(): array
    {
        return $this->requestJson('GET', '/shop/detail', action: 'shop_detail');
    }

    /** @param  array<string, mixed>  $payload */
    public function createOrder(array $payload, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/shipping-order/create', json: $payload, action: 'create_order', orderId: $orderId);

        if (($result['raw']['code'] ?? null) === 200) {
            $result['success'] = true;
            $result['data'] = $result['raw']['data'] ?? null;
        } elseif (isset($result['raw']['code'])) {
            $result['success'] = false;
            $result['message'] = $result['raw']['message'] ?? $result['message'];
        }

        return $result;
    }

    /** @param  array<string, mixed>  $payload */
    public function calculateFee(array $payload, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/shipping-order/fee', json: $payload, action: 'calculate_fee', orderId: $orderId);

        if (($result['raw']['code'] ?? null) === 200) {
            $result['success'] = true;
            $result['data'] = $result['raw']['data'] ?? null;
        }

        return $result;
    }

    public function getOrderDetail(string $orderCode, ?int $orderId = null): array
    {
        $result = $this->requestJson('GET', '/shipping-order/detail', query: ['order_code' => $orderCode], action: 'order_status', orderId: $orderId);

        if (($result['raw']['code'] ?? null) === 200) {
            $result['success'] = true;
            $result['data'] = $result['raw']['data'] ?? null;
        }

        return $result;
    }

    public function cancelOrder(array $payload, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/switch-status/cancel', json: $payload, action: 'cancel_order', orderId: $orderId);

        if (($result['raw']['code'] ?? null) === 200) {
            $result['success'] = true;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function printLabel(string $orderCodes): array
    {
        $result = $this->requestJson('POST', '/a5/gen-token', json: ['order_codes' => [$orderCodes]], action: 'print_label');

        return $result;
    }
}
