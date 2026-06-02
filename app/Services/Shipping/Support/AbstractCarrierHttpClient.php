<?php

namespace App\Services\Shipping\Support;

use App\Models\ShippingApiLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractCarrierHttpClient
{
    abstract protected function provider(): string;

    /** @return array<string, string> */
    abstract protected function headers(): array;

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>|null  $query
     * @return array{success: bool, data: mixed, message: ?string, log_id: ?string, http_status: int}
     */
    protected function requestJson(
        string $method,
        string $endpoint,
        ?array $json = null,
        ?array $query = null,
        string $action = 'request',
        ?int $orderId = null,
    ): array {
        $url = rtrim($this->baseUrl(), '/').$endpoint;

        $pending = Http::timeout(45)->withHeaders($this->headers());

        /** @var Response $response */
        $response = match (strtoupper($method)) {
            'GET' => $pending->get($url, $query ?? []),
            'POST' => $json !== null
                ? $pending->asJson()->post($url, $json)
                : $pending->post($url),
            'PUT' => $pending->asJson()->put($url, $json ?? []),
            default => throw new RuntimeException("Unsupported method {$method}"),
        };

        $body = $response->json();
        $success = $response->successful() && ($body['success'] ?? true) !== false;

        $result = [
            'success' => $success,
            'data' => $body['data'] ?? null,
            'message' => $body['message'] ?? null,
            'log_id' => $body['log_id'] ?? null,
            'http_status' => $response->status(),
            'raw' => $body,
        ];

        if (isset($body['order'])) {
            $result['order'] = $body['order'];
        }

        if (isset($body['fee'])) {
            $result['data'] = $body['fee'];
        }

        $this->log($action, strtoupper($method), $endpoint, $json ?? $query, is_array($body) ? $body : ['raw' => $response->body()], $response->status(), $success, $result['message'], $result['log_id'], $orderId);

        return $result;
    }

    protected function baseUrl(): string
    {
        return app(PartnerCredentialResolver::class)->baseUrl($this->provider());
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    protected function log(
        string $action,
        string $method,
        string $endpoint,
        ?array $request,
        ?array $response,
        int $httpStatus,
        bool $success,
        ?string $message,
        ?string $logId,
        ?int $orderId,
    ): void {
        ShippingApiLog::query()->create([
            'provider' => $this->provider(),
            'order_id' => $orderId,
            'action' => $action,
            'method' => $method,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'success' => $success,
            'message' => $message,
            'request_payload' => $request,
            'response_payload' => $response,
            'log_id' => $logId,
        ]);
    }
}
