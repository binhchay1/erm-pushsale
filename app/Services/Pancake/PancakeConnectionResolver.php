<?php

namespace App\Services\Pancake;

use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;

class PancakeConnectionResolver
{
    public function connection(): IntegrationConnection
    {
        return IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);
    }

    public function client(?IntegrationConnection $connection = null): PancakeApiClient
    {
        $connection ??= $this->connection();
        $credentials = $connection->credentials ?? [];

        return new PancakeApiClient(
            apiKey: $this->credential($credentials, 'api_key'),
            shopId: $this->credential($credentials, 'shop_id'),
            baseUrl: $this->credential($credentials, 'base_url') ?: 'https://pos.pages.fm/api/v1',
            pageAccessToken: $this->credential($credentials, 'page_access_token'),
            pageId: $this->credential($credentials, 'page_id'),
            pageApiBaseUrl: $this->credential($credentials, 'page_api_base_url') ?: 'https://pages.fm/api/public_api/v1',
        );
    }

    /**
     * Ưu tiên credentials lưu trong DB, fallback về default/env trong config.
     * Nhờ vậy có thể cấu hình Pancake bằng UI hoặc .env đều chạy được.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function credential(array $credentials, string $key): ?string
    {
        $value = $credentials[$key]
            ?? config("integrations.platforms.pancake.fields.{$key}.default");

        return filled($value) ? (string) $value : null;
    }
}
