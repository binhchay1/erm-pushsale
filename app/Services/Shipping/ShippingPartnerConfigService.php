<?php

namespace App\Services\Shipping;

use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class ShippingPartnerConfigService
{
    public function __construct(
        private readonly PartnerCredentialResolver $credentials,
        private readonly CarrierRegistry $registry,
    ) {}

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

        if (is_array($payload['credentials'] ?? null)) {
            $filled = array_filter($payload['credentials'], fn ($v) => filled($v));
            if ($filled !== []) {
                $merged = $connection->credentials ?? [];
                foreach ($filled as $key => $value) {
                    $merged[$key] = (string) $value;
                }
                $updates['credentials'] = $merged;
            }
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
            ->map(function (array $field, string $key) use ($stored, $merged, $provider) {
                $value = $merged[$key] ?? null;
                $isSet = filled($value);
                $isSecret = (bool) ($field['secret'] ?? false);

                return [
                    'key' => $key,
                    'label' => Lang::has("shipping_partners.providers.{$provider}.fields.{$key}")
                        ? __("shipping_partners.providers.{$provider}.fields.{$key}")
                        : ($field['label'] ?? $key),
                    'is_secret' => $isSecret,
                    'is_set' => $isSet,
                    'source' => filled($stored[$key] ?? null) ? 'db' : ($isSet ? 'env' : null),
                    'masked' => $isSet && $isSecret ? Str::mask((string) $value, '*', 3) : null,
                    'value' => $isSet && ! $isSecret ? (string) $value : null,
                ];
            })
            ->values()
            ->all();

        $webhookSecret = $connection->webhook_secret;

        $services = collect($meta['services'] ?? [])->map(function (array $service) use ($provider) {
            $code = $service['code'] ?? '';
            $label = Lang::has("shipping_partners.providers.{$provider}.services.{$code}")
                ? __("shipping_partners.providers.{$provider}.services.{$code}")
                : ($service['label'] ?? $code);

            return ['code' => $code, 'label' => $label];
        })->values()->all();

        return [
            'provider' => $provider,
            'label' => Lang::has("shipping_partners.providers.{$provider}.label")
                ? __("shipping_partners.providers.{$provider}.label")
                : ($meta['label'] ?? $provider),
            'description' => Lang::has("shipping_partners.providers.{$provider}.description")
                ? __("shipping_partners.providers.{$provider}.description")
                : ($meta['description'] ?? null),
            'docs_url' => $meta['docs_url'] ?? null,
            'api_base_url' => $meta['api_base_url'] ?? null,
            'services' => $services,
            'is_enabled' => $connection->is_enabled,
            'is_configured' => collect($fields)->every(fn (array $f) => $f['is_set']),
            'webhook_secret_set' => filled($webhookSecret),
            'webhook_secret_masked' => filled($webhookSecret) ? Str::mask((string) $webhookSecret, '*', 2) : null,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'webhook_url' => url("/api/v1/shipping/webhooks/{$provider}"),
            'fields' => $fields,
            'test_actions' => $this->testActionsFor($provider),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    protected function testActionsFor(string $provider): array
    {
        if (! $this->registry->has($provider)) {
            return [];
        }

        return collect($this->registry->get($provider)->testActions())
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => Lang::has("shipping_partners.test_actions.{$key}")
                    ? __("shipping_partners.test_actions.{$key}")
                    : $label,
            ])
            ->values()
            ->all();
    }
}
