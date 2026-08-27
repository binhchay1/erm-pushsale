<?php

namespace App\Support;

/** Danh sách đơn vị vận chuyển từ config để dùng cho UI & validate. */
final class ShippingProviders
{
    /** Provider gateway (NetShip…) — cấu hình ở 1.4 nhưng không chọn trên đơn. */
    public static function isGateway(string $provider): bool
    {
        $meta = config("shipping_partners.providers.{$provider}", []);

        return (bool) ($meta['is_gateway'] ?? false)
            || ($meta['integration_mode'] ?? null) === 'gateway'
            || ($meta['selectable'] ?? true) === false && ($meta['integration_mode'] ?? null) === 'gateway';
    }

    /** @return array<string, array<string, mixed>> */
    public static function selectableProviders(): array
    {
        return collect(config('shipping_partners.providers', []))
            ->reject(fn ($meta, $key) => self::isGateway((string) $key))
            ->all();
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_map('strval', array_keys(self::selectableProviders()));
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::selectableProviders())
            ->map(fn ($provider, $key) => [
                'value' => (string) $key,
                'label' => $provider['label'] ?? (string) $key,
            ])
            ->values()
            ->all();
    }

    public static function label(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return config("shipping_partners.providers.{$value}.label", $value);
    }

    /**
     * Danh sách dịch vụ vận chuyển theo từng đơn vị (dùng cho select "Dịch vụ vận chuyển").
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function serviceOptions(): array
    {
        return collect(self::selectableProviders())
            ->mapWithKeys(fn ($provider, $key) => [
                (string) $key => collect($provider['services'] ?? [])
                    ->map(fn ($s) => ['value' => (string) $s['code'], 'label' => (string) $s['label']])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    public static function serviceLabel(?string $provider, ?string $code): ?string
    {
        if (! $provider || ! $code) {
            return null;
        }

        foreach (config("shipping_partners.providers.{$provider}.services", []) as $service) {
            if ((string) $service['code'] === (string) $code) {
                return (string) $service['label'];
            }
        }

        return $code;
    }
}
