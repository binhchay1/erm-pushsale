<?php

namespace App\Services\Shipping;

use App\Models\Company;
use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use App\Support\ShippingProviders;
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
            ->sortByDesc(fn (array $row) => (int) ($row['is_gateway'] ?? false))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function defaultConfig(?int $companyId): array
    {
        $company = $companyId ? Company::query()->find($companyId) : null;
        $provider = $company?->default_shipping_provider ?: 'manual';
        $services = config("shipping_partners.providers.{$provider}.services", []);

        return [
            'provider' => $provider,
            'method' => $company?->default_shipping_method
                ?: ($services[0]['code'] ?? 'standard'),
            'provider_options' => collect(ShippingProviders::selectableProviders())
                ->map(fn (array $meta, string $key) => [
                    'value' => $key,
                    'label' => $meta['label'] ?? Str::headline($key),
                ])->values()->all(),
            'service_options' => collect($services)->map(fn (array $service) => [
                'value' => $service['code'] ?? 'standard',
                'label' => $service['label'] ?? ($service['code'] ?? 'standard'),
            ])->values()->all(),
        ];
    }

    public function updateDefault(?int $companyId, string $provider, ?string $method): void
    {
        if (! $companyId || ! array_key_exists($provider, config('shipping_partners.providers', []))) {
            return;
        }

        if (ShippingProviders::isGateway($provider)) {
            return;
        }

        Company::query()->whereKey($companyId)->update([
            'default_shipping_provider' => $provider,
            'default_shipping_method' => $method ?: null,
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function update(string $provider, array $payload): ShippingPartnerConnection
    {
        $connection = ShippingPartnerConnection::forProvider($provider);
        $updates = [];

        if (array_key_exists('is_enabled', $payload)) {
            $updates['is_enabled'] = filter_var($payload['is_enabled'], FILTER_VALIDATE_BOOL);
        }

        if (array_key_exists('integration_mode', $payload) && filled($payload['integration_mode'])) {
            $updates['integration_mode'] = (string) $payload['integration_mode'];
        }

        if (! empty($payload['webhook_secret'])) {
            $updates['webhook_secret'] = (string) $payload['webhook_secret'];
        }

        if (array_key_exists('credentials', $payload) && is_array($payload['credentials'] ?? null)) {
            $credentials = $connection->credentials ?? [];
            foreach ($payload['credentials'] as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $credentials[(string) $key] = (string) $value;
            }
            $updates['credentials'] = $credentials;
        }

        if (is_array($payload['settings'] ?? null)) {
            $settings = array_merge($connection->settings ?? [], $payload['settings']);
            foreach (['insurance_enabled', 'allow_partial_delivery', 'auto_create_waybill', 'auto_restock_return', 'use_carrier_cod', 'callback_url_enabled', 'allow_insurance_order'] as $key) {
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOL);
                }
            }
            $settings['extra_services'] = collect($settings['extra_services'] ?? [])
                ->filter(fn ($value) => filled($value))
                ->values()->all();
            $updates['settings'] = $settings;
        }

        if ($updates !== []) {
            $connection->update($updates);
        }

        return $connection->fresh();
    }

    /** @param array<string, mixed> $meta @return array<string, mixed> */
    protected function buildProviderRow(string $provider, array $meta): array
    {
        $connection = ShippingPartnerConnection::forProvider($provider);
        $stored = $connection->credentials ?? [];
        $merged = $this->credentials->mergeCredentials($provider, $stored);

        $fields = collect($meta['fields'] ?? [])->map(function (array $field, string $key) use ($stored, $merged, $provider) {
            $value = $merged[$key] ?? null;
            $isSet = filled($value);
            $isSecret = (bool) ($field['secret'] ?? false);

            return [
                'key' => $key,
                'label' => Lang::has("shipping_partners.providers.{$provider}.fields.{$key}")
                    ? __("shipping_partners.providers.{$provider}.fields.{$key}")
                    : ($field['label'] ?? $key),
                'is_secret' => $isSecret,
                'required' => (bool) ($field['required'] ?? false),
                'is_set' => $isSet,
                'source' => filled($stored[$key] ?? null) ? 'db' : ($isSet ? 'env' : null),
                'masked' => $isSet && $isSecret ? Str::mask((string) $value, '*', 3) : null,
                'value' => $isSet && ! $isSecret ? (string) $value : null,
            ];
        })->values()->all();

        $webhookSecret = $connection->webhook_secret;
        $settings = array_merge(config('shipping_partners.default_settings', []), $connection->settings ?? []);
        $services = collect($meta['services'] ?? [])->map(function (array $service) use ($provider) {
            $code = $service['code'] ?? '';
            return [
                'code' => $code,
                'label' => Lang::has("shipping_partners.providers.{$provider}.services.{$code}")
                    ? __("shipping_partners.providers.{$provider}.services.{$code}")
                    : ($service['label'] ?? $code),
            ];
        })->values()->all();

        return [
            'provider' => $provider,
            'label' => $meta['label'] ?? Str::headline($provider),
            'description' => $meta['description'] ?? null,
            'docs_url' => $meta['docs_url'] ?? null,
            'api_base_url' => $this->credentials->baseUrl($provider),
            'integration_mode' => $connection->integration_mode ?: ($meta['integration_mode'] ?? 'direct'),
            'is_gateway' => ShippingProviders::isGateway($provider),
            'selectable' => ! ShippingProviders::isGateway($provider),
            'routed_providers' => $meta['routed_providers'] ?? [],
            'services' => $services,
            'settings' => $settings,
            'is_enabled' => (bool) $connection->is_enabled,
            'is_configured' => $this->credentials->isReady($provider),
            'webhook_secret_set' => filled($webhookSecret),
            'webhook_secret_masked' => filled($webhookSecret) ? Str::mask((string) $webhookSecret, '*', 2) : null,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'webhook_url' => url("/api/v1/shipping/webhooks/{$provider}"),
            'fields' => $fields,
            'test_actions' => $this->testActionsFor($provider),
        ];
    }

    /** @return list<array{key:string,label:string}> */
    protected function testActionsFor(string $provider): array
    {
        if ($provider === 'netship') {
            $proxy = $this->registry->netShipGateway()->proxyFor(
                array_key_first(config('shipping_partners.providers.netship.routed_providers', ['viettel_post' => 'VTP']))
                    ?: 'viettel_post'
            );

            return collect($proxy->testActions())
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()->all();
        }

        if (! $this->registry->has($provider)) {
            return [];
        }

        return collect($this->registry->get($provider)->testActions())
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()->all();
    }
}
