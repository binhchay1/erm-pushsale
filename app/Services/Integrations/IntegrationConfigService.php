<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use Illuminate\Support\Arr;

class IntegrationConfigService
{
    /** @return list<array<string, mixed>> */
    public function listForApi(): array
    {
        $platforms = config('integrations.platforms', []);

        return collect($platforms)->map(function (array $meta, string $key) {
            $connection = IntegrationConnection::forPlatform(IntegrationPlatform::from($key));
            $envKeys = Arr::get($meta, 'env', []);
            $configured = collect($envKeys)->every(function (string $envVar, string $credKey) use ($connection) {
                if (filled(env($envVar))) {
                    return true;
                }

                return filled($connection->credentials[$credKey] ?? null);
            });

            return [
                'platform' => $key,
                'label' => $meta['label'] ?? $key,
                'is_enabled' => $connection->is_enabled,
                'is_configured' => $configured || $connection->is_enabled,
                'webhook_url' => url("/api/v1/webhooks/{$meta['webhook_path']}"),
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                'required_env' => array_values($envKeys),
                'docs_url' => $meta['docs'] ?? null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function update(IntegrationPlatform $platform, array $credentials, bool $enabled = true): IntegrationConnection
    {
        $connection = IntegrationConnection::forPlatform($platform);
        $connection->update([
            'credentials' => array_merge($connection->credentials ?? [], $credentials),
            'is_enabled' => $enabled,
        ]);

        return $connection->fresh();
    }
}
