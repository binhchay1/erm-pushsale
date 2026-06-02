<?php

namespace App\Services\Shipping\Carriers\Ghtk;

use App\Services\Shipping\Support\AbstractCarrierHttpClient;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingHttpSsl;
use Illuminate\Support\Facades\Http;

/**
 * Client GHTK Open API — tham chiếu https://api.ghtk.vn/docs/submit-order/logistic-overview
 */
class GhtkApiClient extends AbstractCarrierHttpClient
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    protected function provider(): string
    {
        return 'ghtk';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $creds = $this->credentials->credentials('ghtk')['credentials'];

        return array_filter([
            'Token' => $creds['token'] ?? null,
            'X-Client-Source' => $creds['partner_code'] ?? 'saleops',
            'Content-Type' => 'application/json',
        ]);
    }

    /** @return array<string, mixed> */
    public function authenticate(): array
    {
        return $this->requestJson('POST', '/services/authenticated', action: 'authenticate');
    }

    /** @return array<string, mixed> */
    public function listPickAddresses(): array
    {
        return $this->requestJson('GET', '/services/shipment/list_pick_add', action: 'list_pick_addresses');
    }

    /** @return array<string, mixed> */
    public function listProducts(): array
    {
        return $this->requestJson('GET', '/services/shipment/products', action: 'list_products');
    }

    /** @return array<string, mixed> */
    public function listSolutions(): array
    {
        return $this->requestJson('GET', '/services/shipment/solutions', action: 'list_solutions');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, ?int $orderId = null): array
    {
        return $this->requestJson('POST', '/services/shipment/order?ver=1.5', json: $payload, action: 'create_order', orderId: $orderId);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function calculateFee(array $query, ?int $orderId = null): array
    {
        return $this->requestJson('GET', '/services/shipment/fee', query: $query, action: 'calculate_fee', orderId: $orderId);
    }

    /** @return array<string, mixed> */
    public function getOrderStatus(string $trackingKey, ?int $orderId = null): array
    {
        return $this->requestJson('GET', '/services/shipment/v2/'.rawurlencode($trackingKey), action: 'order_status', orderId: $orderId);
    }

    /** @return array<string, mixed> */
    public function cancelOrder(string $trackingKey, ?int $orderId = null): array
    {
        return $this->requestJson('POST', '/services/shipment/cancel/'.rawurlencode($trackingKey), action: 'cancel_order', orderId: $orderId);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{success: bool, message: ?string, http_status: int, binary?: ?string, content_type?: string}
     */
    public function printLabel(string $trackingKey, array $query = [], ?int $orderId = null): array
    {
        $endpoint = '/services/label/'.rawurlencode($trackingKey);
        $url = rtrim($this->baseUrl(), '/').$endpoint;

        $response = Http::timeout(45)
            ->withHeaders($this->headers())
            ->withOptions(['verify' => ShippingHttpSsl::verifyOption()])
            ->get($url, $query);

        $contentType = $response->header('Content-Type') ?? '';
        $isPdf = str_contains($contentType, 'pdf');
        $message = $isPdf ? 'OK' : ($response->json('message') ?? $response->body());

        $this->log(
            action: 'print_label',
            method: 'GET',
            endpoint: $endpoint,
            request: $query,
            response: $isPdf ? ['content_type' => $contentType, 'size' => strlen($response->body())] : $response->json(),
            httpStatus: $response->status(),
            success: $response->successful() && $isPdf,
            message: is_string($message) ? $message : null,
            logId: $response->json('log_id'),
            orderId: $orderId,
        );

        return [
            'success' => $response->successful() && $isPdf,
            'message' => is_string($message) ? $message : null,
            'http_status' => $response->status(),
            'binary' => $isPdf ? $response->body() : null,
            'content_type' => $contentType,
        ];
    }
}
