<?php

namespace App\Services\Shipping\Support;

use App\Models\ShippingPartnerConnection;

class PartnerCredentialResolver
{
    /** @return array<string, mixed> */
    public function credentials(string $provider): array
    {
        $connection = ShippingPartnerConnection::forProvider($provider);

        return [
            'credentials' => array_filter(
                $this->mergeCredentials($provider, $connection->credentials ?? []),
                fn ($v) => $v !== null && $v !== '',
            ),
            'is_enabled' => (bool) $connection->is_enabled,
            'base_url' => $this->baseUrl($provider),
        ];
    }

    /**
     * Hợp nhất credential theo thứ tự ưu tiên: DB → mặc định .env (config) → null.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function mergeCredentials(string $provider, array $stored): array
    {
        $fields = config("shipping_partners.providers.{$provider}.fields", []);

        $merged = [];
        foreach ($fields as $key => $field) {
            $merged[$key] = $stored[$key] ?? ($field['default'] ?? null);
        }

        return $merged;
    }

    public function isReady(string $provider): bool
    {
        $pack = $this->credentials($provider);

        if (! $pack['is_enabled']) {
            return false;
        }

        return match ($provider) {
            'ghtk' => filled($pack['credentials']['token'] ?? null),
            'ghn' => filled($pack['credentials']['token'] ?? null)
                && filled($pack['credentials']['shop_id'] ?? null),
            'viettel_post' => filled($pack['credentials']['token'] ?? null)
                || (filled($pack['credentials']['username'] ?? null) && filled($pack['credentials']['password'] ?? null)),
            'jnt' => filled($pack['credentials']['api_key'] ?? null)
                && filled($pack['credentials']['api_secret'] ?? null),
            'spx' => filled($pack['credentials']['user_id'] ?? null)
                && filled($pack['credentials']['secret_key'] ?? null)
                && filled($pack['base_url'] ?? null),
            default => false,
        };
    }

    public function baseUrl(string $provider): string
    {
        $meta = config("shipping_partners.providers.{$provider}", []);

        if ($provider === 'ghtk' && ($meta['use_sandbox'] ?? false)) {
            return $meta['api_staging_url'] ?? 'https://services-staging.ghtklab.com';
        }

        return $meta['api_base_url'] ?? '';
    }

    public function trackingUrl(string $provider, string $code): ?string
    {
        $template = config("shipping_partners.providers.{$provider}.tracking_url");

        return $template ? str_replace('{code}', $code, $template) : null;
    }
}
