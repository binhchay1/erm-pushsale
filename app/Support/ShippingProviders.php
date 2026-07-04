<?php

namespace App\Support;

/** Danh sách đơn vị vận chuyển từ config để dùng cho UI & validate. */
final class ShippingProviders
{
    /** @return list<string> */
    public static function keys(): array
    {
        return array_map('strval', array_keys((array) config('shipping_partners.providers', [])));
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(config('shipping_partners.providers', []))
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
        return collect(config('shipping_partners.providers', []))
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
