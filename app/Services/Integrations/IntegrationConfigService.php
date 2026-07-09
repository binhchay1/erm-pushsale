<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class IntegrationConfigService
{
    /** @return array<string, mixed> */
    public function hubOverview(): array
    {
        $hub = config('integrations.hub', []);

        return [
            'title' => __('integrations.hub.title'),
            'summary' => __('integrations.hub.summary'),
            'problems' => collect($hub['problems'] ?? [])->map(fn ($_, $i) => __("integrations.hub.problems.{$i}"))->values()->all(),
            'solutions' => collect($hub['solutions'] ?? [])->mapWithKeys(fn ($_, $key) => [$key => __("integrations.hub.solutions.{$key}")])->all(),
            'workflow' => collect($hub['workflow'] ?? [])->map(fn ($_, $i) => __("integrations.hub.workflow.{$i}"))->values()->all(),
        ];
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        return collect(config('integrations.categories', []))
            ->mapWithKeys(fn ($_, $key) => [$key => __("integrations.categories.{$key}")])
            ->all();
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

        return $this->buildPlatformRow($platform->value, $meta);
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
                    ->filter(fn ($def) => (bool) ($def['required'] ?? true))
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
    protected function buildPlatformRow(string $key, array $meta): array
    {
        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::from($key));
        $fields = $this->fieldStates($key, $connection);

        $verifyValue = $key === 'facebook'
            ? ($connection->verify_token ?? env('FACEBOOK_VERIFY_TOKEN'))
            : null;

        $webhookSecret = $connection->webhook_secret ?? config('integrations.webhook.global_secret');

        return [
            'platform' => $key,
            'label' => __("integrations.platforms.{$key}.label"),
            'category' => $meta['category'] ?? 'advertising',
            'category_label' => __("integrations.categories.{$meta['category']}"),
            'description' => __("integrations.platforms.{$key}.description"),
            'is_enabled' => $connection->is_enabled,
            'is_configured' => collect($fields)
                ->filter(fn ($f) => $f['is_required'])
                ->every(fn ($f) => $f['is_set']),
            'webhook_url' => $connection->webhookUrl(),
            'api_leads_url' => url('/api/v1/leads'),
            'pancake_extension_url' => $key === 'pancake' ? url('/api/v1/pancake/extension/orders') : null,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'docs_url' => $meta['docs'] ?? null,
            'verify_token_set' => filled($verifyValue),
            'verify_token_masked' => filled($verifyValue) ? Str::mask((string) $verifyValue, '*', 2) : null,
            'webhook_secret_set' => filled($webhookSecret),
            'webhook_secret_masked' => filled($webhookSecret) ? Str::mask((string) $webhookSecret, '*', 2) : null,
            'fields' => $fields,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fieldStates(string $platformKey, IntegrationConnection $connection): array
    {
        $fieldDefs = config("integrations.platforms.{$platformKey}.fields", []);

        return collect($fieldDefs)->map(function (array $def, string $credKey) use ($connection) {
            $envVar = $def['env'] ?? '';
            $defaultValue = $def['default'] ?? (filled($envVar) ? env($envVar) : null);
            $fromDb = filled($connection->credentials[$credKey] ?? null);
            $fromEnv = ! $fromDb && filled($defaultValue);
            $isSet = $fromDb || $fromEnv;
            $isSecret = (bool) ($def['secret'] ?? false);
            $resolved = $fromDb
                ? (string) ($connection->credentials[$credKey] ?? '')
                : (string) ($defaultValue ?? '');

            $row = [
                'key' => $credKey,
                'label' => Lang::has("integrations.fields.{$credKey}")
                    ? __("integrations.fields.{$credKey}")
                    : ($def['label'] ?? $credKey),
                'env' => $envVar,
                'is_secret' => $isSecret,
                'is_set' => $isSet,
                'is_required' => (bool) ($def['required'] ?? true),
                'source' => $fromDb ? 'db' : ($fromEnv ? 'env' : null),
            ];

            if ($isSet && ! $isSecret) {
                $row['value'] = $resolved;
            } elseif ($isSet && $isSecret) {
                $row['masked'] = Str::mask($resolved ?: '********', '*', 3);
            }

            return $row;
        })->values()->all();
    }
}
