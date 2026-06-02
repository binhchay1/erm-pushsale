<?php

namespace App\Services\Shipping\Carriers\Jnt;

use App\Services\Shipping\Support\AbstractCarrierHttpClient;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use Illuminate\Support\Facades\Http;

class JntApiClient extends AbstractCarrierHttpClient
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    protected function provider(): string
    {
        return 'jnt';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        return ['Content-Type' => 'application/x-www-form-urlencoded'];
    }

    /** @param  array<string, mixed>  $biz */
    public function signedRequest(string $endpoint, array $biz, string $action, ?int $orderId = null): array
    {
        $creds = $this->credentials->credentials('jnt')['credentials'];
        $apiKey = (string) ($creds['api_key'] ?? '');
        $secret = (string) ($creds['api_secret'] ?? '');
        $customerCode = (string) ($creds['client_code'] ?? '');

        $bizContent = json_encode($biz, JSON_UNESCAPED_UNICODE);
        $digest = base64_encode(md5($bizContent.$secret, true));

        $payload = [
            'logistics_interface' => $bizContent,
            'data_digest' => $digest,
            'msg_type' => 'ORDERCREATE',
            'eccompanyid' => $customerCode,
            'customerid' => $customerCode,
        ];

        $url = rtrim($this->baseUrl(), '/').$endpoint;
        $response = Http::timeout(45)->asForm()->post($url, $payload);
        $body = $response->json();

        $success = $response->successful() && (($body['responseitems'][0]['success'] ?? false) === true || ($body['success'] ?? false) === true);

        $this->log($action, 'POST', $endpoint, $biz, is_array($body) ? $body : ['raw' => $response->body()], $response->status(), $success, is_array($body) ? ($body['responseitems'][0]['reason'] ?? null) : null, null, $orderId);

        return [
            'success' => $success,
            'data' => $body['responseitems'][0] ?? $body,
            'message' => $body['responseitems'][0]['reason'] ?? $body['message'] ?? null,
            'http_status' => $response->status(),
            'raw' => $body,
        ];
    }

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        return [
            'success' => $this->credentials->isReady('jnt'),
            'message' => $this->credentials->isReady('jnt')
                ? 'Credentials J&T đã cấu hình (chưa gọi endpoint thật).'
                : 'Thiếu api_key / api_secret.',
            'data' => null,
            'http_status' => 200,
        ];
    }
}
