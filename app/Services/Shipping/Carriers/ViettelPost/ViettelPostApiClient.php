<?php

namespace App\Services\Shipping\Carriers\ViettelPost;

use App\Services\Shipping\Support\AbstractCarrierHttpClient;
use App\Services\Shipping\Support\PartnerCredentialResolver;

class ViettelPostApiClient extends AbstractCarrierHttpClient
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    protected function provider(): string
    {
        return 'viettel_post';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $creds = $this->credentials->credentials('viettel_post')['credentials'];

        return array_filter([
            'Token' => $creds['token'] ?? null,
            'Content-Type' => 'application/json',
        ]);
    }

    /** @return array<string, mixed> */
    public function login(): array
    {
        $creds = $this->credentials->credentials('viettel_post')['credentials'];

        return $this->requestJson('POST', '/user/Login', json: [
            'USERNAME' => $creds['username'] ?? '',
            'PASSWORD' => $creds['password'] ?? '',
        ], action: 'login');
    }

    /** @param  array<string, mixed>  $payload */
    public function createOrder(array $payload, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/order/createOrder', json: $payload, action: 'create_order', orderId: $orderId);

        if (($result['raw']['status'] ?? null) === 200 || ($result['raw']['error'] ?? true) === false) {
            $result['success'] = true;
            $result['data'] = $result['raw']['data'] ?? $result['raw'];
        }

        return $result;
    }

    /** @param  array<string, mixed>  $payload */
    public function calculateFee(array $payload, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/order/getPriceAll', json: $payload, action: 'calculate_fee', orderId: $orderId);

        if (($result['raw']['status'] ?? null) === 200) {
            $result['success'] = true;
            $result['data'] = $result['raw']['data'] ?? null;
        }

        return $result;
    }

    public function getOrder(string $orderNumber, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/order/getOrderInfo', json: [
            'TYPE' => 1,
            'ORDER_NUMBER' => $orderNumber,
        ], action: 'order_status', orderId: $orderId);

        if (($result['raw']['status'] ?? null) === 200) {
            $result['success'] = true;
            $result['data'] = $result['raw']['data'] ?? null;
        }

        return $result;
    }

    public function cancelOrder(string $orderNumber, ?int $orderId = null): array
    {
        $result = $this->requestJson('POST', '/order/UpdateOrder', json: [
            'TYPE' => 4,
            'ORDER_NUMBER' => $orderNumber,
            'NOTE' => 'Hủy từ SaleOps',
        ], action: 'cancel_order', orderId: $orderId);

        if (($result['raw']['status'] ?? null) === 200) {
            $result['success'] = true;
        }

        return $result;
    }
}
