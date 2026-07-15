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
            'settings' => array_merge(
                config('shipping_partners.default_settings', []),
                $connection->settings ?? [],
            ),
            'integration_mode' => $connection->integration_mode
                ?: config("shipping_partners.providers.{$provider}.integration_mode", 'direct'),
            'is_enabled' => (bool) $connection->is_enabled,
            'base_url' => $this->baseUrl($provider),
        ];
    }

    /**
     * Hợp nhất credential theo thứ tự ưu tiên: DB → mặc định .env/config → null.
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

        if (($pack['integration_mode'] ?? null) === 'manual' || $provider === 'manual') {
            return true;
        }

        $fields = config("shipping_partners.providers.{$provider}.fields", []);
        $required = collect($fields)
            ->filter(fn (array $field) => (bool) ($field['required'] ?? false))
            ->keys();

        if ($required->isEmpty()) {
            return false;
        }

        foreach ($required as $key) {
            if ($key === 'base_url') {
                if (! filled($pack['base_url'] ?? null)) {
                    return false;
                }
                continue;
            }

            if (! filled($pack['credentials'][$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function baseUrl(string $provider): string
    {
        $meta = config("shipping_partners.providers.{$provider}", []);
        $connection = ShippingPartnerConnection::forProvider($provider);
        $stored = $connection->credentials ?? [];

        if (filled($stored['base_url'] ?? null)) {
            return rtrim((string) $stored['base_url'], '/');
        }

        if ($provider === 'ghtk' && ($meta['use_sandbox'] ?? false)) {
            return rtrim((string) ($meta['api_staging_url'] ?? 'https://services-staging.ghtklab.com'), '/');
        }

        return rtrim((string) ($meta['api_base_url'] ?? ''), '/');
    }

    /** @return array<string, mixed> */
    public function settings(string $provider): array
    {
        return $this->credentials($provider)['settings'];
    }

    public function trackingUrl(string $provider, string $code): ?string
    {
        $template = config("shipping_partners.providers.{$provider}.tracking_url");

        return $template ? str_replace('{code}', urlencode($code), $template) : null;
    }
}
