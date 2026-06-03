<?php

namespace App\Services\Shipping\Carriers\Spx;

use App\Services\Shipping\Support\AbstractCarrierHttpClient;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Services\Shipping\Support\ShippingHttpSsl;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SpxApiClient extends AbstractCarrierHttpClient
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    protected function provider(): string
    {
        return 'spx';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $payload */
    public function createOrder(array $payload, ?int $orderId = null): array
    {
        return $this->requestSpx('POST', 'create_order_path', $payload, 'create_order', $orderId);
    }

    /** @param  array<string, mixed>  $payload */
    public function getOrderDetail(array $payload, ?int $orderId = null): array
    {
        return $this->requestSpx('POST', 'order_detail_path', $payload, 'order_detail', $orderId);
    }

    /** @param  array<string, mixed>  $payload */
    public function calculateFee(array $payload, ?int $orderId = null): array
    {
        return $this->requestSpx('POST', 'fee_path', $payload, 'calculate_fee', $orderId);
    }

    /** @param  array<string, mixed>  $payload */
    public function cancelOrder(array $payload, ?int $orderId = null): array
    {
        return $this->requestSpx('POST', 'cancel_order_path', $payload, 'cancel_order', $orderId);
    }

    /** @param  array<string, mixed>  $payload */
    public function printLabel(array $payload, ?int $orderId = null): array
    {
        return $this->requestSpx('POST', 'label_path', $payload, 'print_label', $orderId);
    }

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        return $this->requestSpx('POST', 'test_connection_path', ['ping' => true], 'test_connection');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestSpx(
        string $method,
        string $pathCredentialKey,
        array $payload,
        string $action,
        ?int $orderId = null,
    ): array {
        $creds = $this->credentials->credentials('spx')['credentials'];
        $endpoint = trim((string) ($creds[$pathCredentialKey] ?? ''), ' ');

        if ($endpoint === '') {
            return [
                'success' => false,
                'data' => null,
                'message' => "SPX chưa cấu hình {$pathCredentialKey}.",
                'http_status' => 422,
                'raw' => null,
            ];
        }

        $apiVersion = trim((string) ($creds['api_version'] ?? 'v1'), '/');
        $normalizedEndpoint = '/'.ltrim($endpoint, '/');
        if ($apiVersion !== '' && ! str_starts_with($normalizedEndpoint, '/'.$apiVersion.'/')) {
            $normalizedEndpoint = '/'.$apiVersion.$normalizedEndpoint;
        }

        $timestamp = (string) now()->timestamp;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $userId = (string) ($creds['user_id'] ?? '');
        $secretKey = (string) ($creds['secret_key'] ?? '');
        $accountId = (string) ($creds['account_id'] ?? '');
        $signatureHeader = (string) ($creds['signature_header'] ?? 'X-Signature');
        $timestampHeader = (string) ($creds['timestamp_header'] ?? 'X-Timestamp');

        $signaturePayload = $userId.'|'.$timestamp.'|'.$normalizedEndpoint.'|'.$body;
        $signature = hash_hmac('sha256', $signaturePayload, $secretKey);
        $url = rtrim($this->baseUrl(), '/').$normalizedEndpoint;

        $headers = array_filter([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-User-Id' => $userId,
            'X-Account-Id' => $accountId ?: null,
            $timestampHeader => $timestamp,
            $signatureHeader => $signature,
        ], fn ($v) => $v !== null && $v !== '');

        $pending = Http::timeout(45)
            ->withHeaders($headers)
            ->withOptions(['verify' => ShippingHttpSsl::verifyOption()]);

        /** @var Response $response */
        $response = match (strtoupper($method)) {
            'GET' => $pending->get($url, $payload),
            default => $pending->asJson()->post($url, $payload),
        };

        $raw = $response->json();
        $normalized = is_array($raw) ? $raw : ['body' => $response->body()];
        $successFlag = $normalized['success'] ?? $normalized['ok'] ?? null;
        $statusCode = $normalized['code'] ?? $normalized['status_code'] ?? null;
        $success = $response->successful()
            && $successFlag !== false
            && ! (is_numeric($statusCode) && (int) $statusCode !== 0);

        $message = $normalized['message']
            ?? $normalized['msg']
            ?? (isset($normalized['error']) ? (string) $normalized['error'] : null);
        $data = $normalized['data'] ?? $normalized['result'] ?? $normalized['response'] ?? $normalized;

        $this->log(
            action: $action,
            method: strtoupper($method),
            endpoint: $normalizedEndpoint,
            request: $payload,
            response: $normalized,
            httpStatus: $response->status(),
            success: $success,
            message: is_string($message) ? $message : null,
            logId: isset($normalized['request_id']) ? (string) $normalized['request_id'] : null,
            orderId: $orderId,
        );

        return [
            'success' => $success,
            'data' => $data,
            'message' => is_string($message) ? $message : null,
            'http_status' => $response->status(),
            'raw' => $normalized,
        ];
    }
}
