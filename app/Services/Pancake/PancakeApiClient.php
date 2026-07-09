<?php

namespace App\Services\Pancake;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PancakeApiClient
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $shopId = null,
        protected string $baseUrl = 'https://pos.pages.fm/api/v1',
        protected ?string $pageAccessToken = null,
        protected ?string $pageId = null,
        protected string $pageApiBaseUrl = 'https://pages.fm/api/public_api/v1',
    ) {}

    /** @return array<string, mixed> */
    public function shops(): array
    {
        return $this->get('/shops');
    }

    /** @return array<string, mixed> */
    public function orders(array $params = []): array
    {
        $shopId = $this->requireShopId();

        return $this->get("/shops/{$shopId}/orders", $params);
    }

    /** @return array<string, mixed> */
    public function products(array $params = []): array
    {
        $shopId = $this->requireShopId();

        return $this->get("/shops/{$shopId}/products/variations", $params);
    }

    /** @return array<string, mixed> */
    public function conversationMessages(?string $pageId, string $conversationId, array $params = []): array
    {
        $pageId = $this->resolvePageId($pageId);

        return $this->pageGet("/pages/{$pageId}/conversations/{$conversationId}/messages", $params);
    }

    /** @return array<string, mixed> */
    public function sendConversationMessage(?string $pageId, string $conversationId, string $message): array
    {
        $pageId = $this->resolvePageId($pageId);

        return $this->pagePost("/pages/{$pageId}/conversations/{$conversationId}/messages", [
            'action' => 'reply_inbox',
            'message' => $message,
        ]);
    }

    /** @return array{ok: bool, message: string, shops?: mixed} */
    public function test(): array
    {
        $shops = $this->shops();
        $items = Arr::get($shops, 'data') ?? Arr::get($shops, 'shops') ?? $shops;

        return [
            'ok' => true,
            'message' => 'Kết nối Pancake POS thành công.',
            'shops' => is_array($items) ? array_slice($items, 0, 5) : $items,
        ];
    }

    /** @return array<string, mixed> */
    protected function get(string $path, array $params = []): array
    {
        $response = $this->http()->get(ltrim($path, '/'), array_filter([
            ...$params,
            'api_key' => $this->requireApiKey(),
        ], fn ($value) => $value !== null && $value !== ''));

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Pancake POS API lỗi HTTP %s: %s',
                $response->status(),
                str($response->body())->limit(500)->value(),
            ));
        }

        $json = $response->json();

        return is_array($json) ? $json : ['raw' => $response->body()];
    }

    /** @return array<string, mixed> */
    protected function pageGet(string $path, array $params = []): array
    {
        $response = $this->pageHttp()->get(ltrim($path, '/'), array_filter([
            ...$params,
            ...$this->pageTokenParams(),
        ], fn ($value) => $value !== null && $value !== ''));

        return $this->decodePageResponse($response->status(), $response->body(), $response->json());
    }

    /** @return array<string, mixed> */
    protected function pagePost(string $path, array $payload = []): array
    {
        $response = $this->pageHttp()
            ->withQueryParameters($this->pageTokenParams())
            ->post(ltrim($path, '/'), $payload);

        return $this->decodePageResponse($response->status(), $response->body(), $response->json());
    }

    /** @return array<string, mixed> */
    protected function decodePageResponse(int $status, string $body, mixed $json): array
    {
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'Pancake Page API lỗi HTTP %s: %s',
                $status,
                str($body)->limit(500)->value(),
            ));
        }

        return is_array($json) ? $json : ['raw' => $body];
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300);
    }

    protected function pageHttp(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->pageApiBaseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300);
    }

    /**
     * Pancake public API thường dùng page access token. Một số tài liệu/cụm API
     * gọi tên token khác nhau, nên gửi cả 2 key để giảm rủi ro lệch phiên bản API.
     *
     * @return array<string, string>
     */
    protected function pageTokenParams(): array
    {
        $token = $this->requirePageAccessToken();

        return [
            'page_access_token' => $token,
            'access_token' => $token,
        ];
    }

    protected function requireApiKey(): string
    {
        if (! filled($this->apiKey)) {
            throw new RuntimeException('Chưa cấu hình API Key Pancake POS.');
        }

        return (string) $this->apiKey;
    }

    protected function requireShopId(): string
    {
        if (! filled($this->shopId)) {
            throw new RuntimeException('Chưa cấu hình Shop ID Pancake POS.');
        }

        return (string) $this->shopId;
    }

    protected function requirePageAccessToken(): string
    {
        if (! filled($this->pageAccessToken)) {
            throw new RuntimeException('Chưa cấu hình Page Access Token Pancake để chat với khách hàng.');
        }

        return (string) $this->pageAccessToken;
    }

    protected function resolvePageId(?string $pageId): string
    {
        $pageId = filled($pageId) ? $pageId : $this->pageId;

        if (! filled($pageId)) {
            throw new RuntimeException('Không xác định được Page ID Pancake của hội thoại.');
        }

        return (string) $pageId;
    }
}
