<?php

namespace App\Services\Shipping;

use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use Illuminate\Support\Str;

class ShippingPartnerConfigService
{
    public function __construct(private readonly PartnerCredentialResolver $credentials) {}

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return collect(config('shipping_partners.providers', []))
            ->map(fn (array $meta, string $provider) => $this->buildProviderRow($provider, $meta))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $provider, array $payload): ShippingPartnerConnection
    {
        $connection = ShippingPartnerConnection::forProvider($provider);
        $updates = [];

        if (array_key_exists('is_enabled', $payload)) {
            $updates['is_enabled'] = (bool) $payload['is_enabled'];
        }

        if (! empty($payload['webhook_secret'])) {
            $updates['webhook_secret'] = (string) $payload['webhook_secret'];
        }

        if (! empty($payload['credentials']) && is_array($payload['credentials'])) {
            $merged = $connection->credentials ?? [];
            foreach ($payload['credentials'] as $key => $value) {
                if (filled($value)) {
                    $merged[$key] = $value;
                }
            }
            $updates['credentials'] = $merged;
        }

        if ($updates !== []) {
            $connection->update($updates);
        }

        return $connection->fresh();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildProviderRow(string $provider, array $meta): array
    {
        $connection = ShippingPartnerConnection::forProvider($provider);
        $stored = $connection->credentials ?? [];
        $merged = $this->credentials->mergeCredentials($provider, $stored);

        $fields = collect($meta['fields'] ?? [])
            ->map(function (array $field, string $key) use ($stored, $merged) {
                $value = $merged[$key] ?? null;
                $isSet = filled($value);
                $isSecret = (bool) ($field['secret'] ?? false);

                return [
                    'key' => $key,
                    'label' => $field['label'] ?? $key,
                    'is_secret' => $isSecret,
                    'is_set' => $isSet,
                    'source' => filled($stored[$key] ?? null) ? 'db' : ($isSet ? 'env' : null),
                    'masked' => $isSet && $isSecret ? Str::mask((string) $value, '*', 3) : null,
                    'value' => $isSet && ! $isSecret ? (string) $value : null,
                ];
            })
            ->values()
            ->all();

        return [
            'provider' => $provider,
            'label' => $meta['label'] ?? $provider,
            'description' => $meta['description'] ?? null,
            'docs_url' => $meta['docs_url'] ?? null,
            'api_base_url' => $meta['api_base_url'] ?? null,
            'services' => $meta['services'] ?? [],
            'is_enabled' => $connection->is_enabled,
            'is_configured' => collect($fields)->every(fn (array $f) => $f['is_set']),
            'webhook_secret_set' => filled($connection->webhook_secret),
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'webhook_url' => url("/api/v1/shipping/webhooks/{$provider}"),
            'fields' => $fields,
        ];
    }
}
