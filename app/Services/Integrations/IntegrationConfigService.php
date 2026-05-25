<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IntegrationConfigService
{
    /** @return array<string, mixed> */
    public function hubOverview(): array
    {
        return config('integrations.hub', []);
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        return config('integrations.categories', []);
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        return collect(config('integrations.platforms', []))
            ->map(fn (array $meta, string $key) => $this->buildPlatformRow($key, $meta))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function detailForAdmin(IntegrationPlatform $platform): array
    {
        $meta = config("integrations.platforms.{$platform->value}");

        if (! $meta) {
            abort(404);
        }

        return $this->buildPlatformRow($platform->value, $meta, includeSecrets: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateConnection(IntegrationPlatform $platform, array $data): IntegrationConnection
    {
        $connection = IntegrationConnection::forPlatform($platform);
        $updates = [];

        if (array_key_exists('is_enabled', $data)) {
            $updates['is_enabled'] = (bool) $data['is_enabled'];
        }

        if (filled($data['verify_token'] ?? null)) {
            $updates['verify_token'] = $data['verify_token'];
        }

        if (filled($data['webhook_secret'] ?? null)) {
            $updates['webhook_secret'] = $data['webhook_secret'];
        }

        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $merged = $connection->credentials ?? [];
            foreach ($data['credentials'] as $key => $value) {
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

    public function touchSynced(IntegrationPlatform $platform): void
    {
        IntegrationConnection::forPlatform($platform)->update(['last_synced_at' => now()]);
    }

    public function isEnabled(IntegrationPlatform $platform): bool
    {
        return IntegrationConnection::forPlatform($platform)->is_enabled;
    }

    /** @return list<array<string, mixed>> */
    public function listForApi(): array
    {
        return collect($this->listForAdmin())
            ->map(fn (array $row) => Arr::only($row, [
                'platform', 'label', 'is_enabled', 'is_configured',
                'webhook_url', 'last_synced_at', 'docs_url',
            ]))
            ->map(function (array $row) {
                $row['required_env'] = collect(config("integrations.platforms.{$row['platform']}.fields", []))
                    ->pluck('env')
                    ->values()
                    ->all();

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildPlatformRow(string $key, array $meta, bool $includeSecrets = false): array
    {
        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::from($key));
        $fields = $this->fieldStates($key, $connection, $includeSecrets);

        return [
            'platform' => $key,
            'label' => $meta['label'] ?? $key,
            'category' => $meta['category'] ?? 'advertising',
            'category_label' => config("integrations.categories.{$meta['category']}", $meta['category'] ?? ''),
            'description' => $meta['description'] ?? '',
            'is_enabled' => $connection->is_enabled,
            'is_configured' => collect($fields)->every(fn ($f) => $f['is_set']),
            'webhook_url' => url("/api/v1/webhooks/{$meta['webhook_path']}"),
            'api_leads_url' => url('/api/v1/leads'),
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'docs_url' => $meta['docs'] ?? null,
            'verify_token' => $includeSecrets ? ($connection->verify_token ?? '') : null,
            'verify_token_set' => filled($connection->verify_token)
                || ($key === 'facebook' && filled(env('FACEBOOK_VERIFY_TOKEN'))),
            'webhook_secret_set' => filled($connection->webhook_secret) || filled(config('integrations.webhook.global_secret')),
            'fields' => $fields,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fieldStates(string $platformKey, IntegrationConnection $connection, bool $includeSecrets): array
    {
        $fieldDefs = config("integrations.platforms.{$platformKey}.fields", []);

        return collect($fieldDefs)->map(function (array $def, string $credKey) use ($connection, $includeSecrets) {
            $envVar = $def['env'] ?? '';
            $fromEnv = filled(env($envVar));
            $fromDb = filled($connection->credentials[$credKey] ?? null);
            $isSet = $fromEnv || $fromDb;
            $isSecret = (bool) ($def['secret'] ?? false);

            $row = [
                'key' => $credKey,
                'label' => $def['label'] ?? $credKey,
                'env' => $envVar,
                'is_secret' => $isSecret,
                'is_set' => $isSet,
                'source' => $fromDb ? 'database' : ($fromEnv ? 'env' : null),
            ];

            if ($includeSecrets && $isSet && ! $isSecret) {
                $row['value'] = $fromDb
                    ? ($connection->credentials[$credKey] ?? '')
                    : env($envVar);
            } elseif ($includeSecrets && $isSet && $isSecret) {
                $row['masked'] = Str::mask(
                    $fromDb ? ($connection->credentials[$credKey] ?? '********') : (env($envVar) ?: '********'),
                    '*',
                    3
                );
            }

            return $row;
        })->values()->all();
    }
}
