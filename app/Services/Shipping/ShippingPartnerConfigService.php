<?php

namespace App\Services\Shipping;

use App\Models\ShippingPartnerConnection;
use Illuminate\Support\Str;

class ShippingPartnerConfigService
{
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
        $fields = collect($meta['fields'] ?? [])
            ->map(function (array $field, string $key) use ($connection) {
                $value = $connection->credentials[$key] ?? null;
                $isSet = filled($value);

                return [
                    'key' => $key,
                    'label' => $field['label'] ?? $key,
                    'is_secret' => (bool) ($field['secret'] ?? false),
                    'is_set' => $isSet,
                    'masked' => $isSet && ($field['secret'] ?? false)
                        ? Str::mask((string) $value, '*', 3)
                        : null,
                ];
            })
            ->values()
            ->all();

        return [
            'provider' => $provider,
            'label' => $meta['label'] ?? $provider,
            'description' => $meta['description'] ?? null,
            'docs_url' => $meta['docs_url'] ?? null,
            'is_enabled' => $connection->is_enabled,
            'is_configured' => collect($fields)->every(fn (array $f) => $f['is_set']),
            'webhook_secret_set' => filled($connection->webhook_secret),
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'webhook_url' => url("/api/v1/shipping/webhooks/{$provider}"),
            'fields' => $fields,
        ];
    }
}
