<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;
use App\Models\LandingConnection;
use App\Models\LandingConnectionSource;
use App\Models\Pushsale\PartnerConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PartnerConnectionService
{
    public const ACTIVE_SETTING_KEY = 'partner_providers.active';

    /** @return list<array<string, mixed>> */
    public function providers(): array
    {
        $activeMap = $this->activeMap();

        $items = collect(config('partner_providers', []))
            ->map(function (array $provider, string $slug) use ($activeMap): array {
                return [
                    'slug' => $slug,
                    'name' => (string) ($provider['name'] ?? $slug),
                    'caption' => (string) ($provider['caption'] ?? ($provider['name'] ?? $slug)),
                    'description' => (string) ($provider['description'] ?? ''),
                    'logo' => (string) ($provider['logo'] ?? ''),
                    'sort' => (int) ($provider['sort'] ?? 99),
                    'is_active' => (bool) ($activeMap[$slug] ?? false),
                ];
            })
            ->sortBy('sort')
            ->values()
            ->all();

        return $items;
    }

    public function provider(string $slug): ?array
    {
        foreach ($this->providers() as $provider) {
            if ($provider['slug'] === $slug) {
                return $provider;
            }
        }

        return null;
    }

    public function defaultProviderSlug(): string
    {
        $first = $this->providers()[0] ?? null;

        return (string) ($first['slug'] ?? 'cnvloyalty');
    }

    /** @return array<string, bool> */
    public function activeMap(): array
    {
        $raw = AppSetting::get(self::ACTIVE_SETTING_KEY, '{}') ?: '{}';
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $slug => $value) {
            $map[(string) $slug] = (bool) $value;
        }

        return $map;
    }

    public function setProviderActive(string $slug, bool $active): array
    {
        if ($this->provider($slug) === null) {
            throw ValidationException::withMessages([
                'partner' => 'Đối tác không hợp lệ.',
            ]);
        }

        $map = $this->activeMap();
        $map[$slug] = $active;
        AppSetting::set(self::ACTIVE_SETTING_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));

        return $this->provider($slug) ?? [];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public function listConnections(string $partnerSlug, array $filters = [], int $perPage = 20): array
    {
        $query = PartnerConnection::query()
            ->with([
                'marketer:id,name,email',
                'source:id,name',
                'product:id,name',
                'updater:id,name,email',
                'landingConnection.sources',
                'landingConnection.createdBy:id,name,email',
                'landingConnection.marketer:id,name,email',
                'landingConnection.products.product:id,name',
            ])
            ->where('partner_type', $partnerSlug)
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $keyword = trim((string) $filters['search']);
                $query->where(function ($query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('endpoint_url', 'like', "%{$keyword}%")
                        ->orWhereHas('source', fn ($source) => $source->where('name', 'like', "%{$keyword}%"))
                        ->orWhereHas('landingConnection', fn ($landing) => $landing->where('name', 'like', "%{$keyword}%"));
                });
            })
            ->latest('id');

        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $paginator = $query->paginate(max(10, min(100, $perPage)), ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->values()
                ->map(fn (PartnerConnection $connection, int $index): array => $this->serializeConnection(
                    $connection,
                    ($paginator->firstItem() ?? 1) + $index - 1,
                ))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @param  list<int>  $landingConnectionIds
     * @return list<PartnerConnection>
     */
    public function attachLandingConnections(string $partnerSlug, array $landingConnectionIds, User $actor): array
    {
        if ($this->provider($partnerSlug) === null) {
            throw ValidationException::withMessages([
                'partner' => 'Đối tác không hợp lệ.',
            ]);
        }

        $ids = collect($landingConnectionIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'landing_connection_ids' => 'Chọn ít nhất một nguồn dữ liệu.',
            ]);
        }

        $landings = LandingConnection::query()
            ->with(['sources', 'products', 'marketer:id,name', 'createdBy:id,name'])
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        if ($landings->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'landing_connection_ids' => 'Một số nguồn dữ liệu không tồn tại hoặc không thuộc đơn vị.',
            ]);
        }

        $created = [];

        DB::transaction(function () use ($partnerSlug, $ids, $landings, $actor, &$created): void {
            foreach ($ids as $landingId) {
                /** @var LandingConnection $landing */
                $landing = $landings->get($landingId);
                $existing = PartnerConnection::query()
                    ->where('partner_type', $partnerSlug)
                    ->where('landing_connection_id', $landing->id)
                    ->first();

                if ($existing) {
                    $created[] = $existing;
                    continue;
                }

                $mainSource = $landing->sources
                    ->firstWhere('source_type', LandingConnectionSource::TYPE_MAIN)
                    ?? $landing->sources->first();

                $productId = $landing->products->firstWhere('is_default', true)?->product_id
                    ?? $landing->products->first()?->product_id;

                $token = $this->makePublicToken();
                $plainSecret = Str::random(40);

                $connection = PartnerConnection::query()->create([
                    'name' => $landing->name,
                    'partner_type' => $partnerSlug,
                    'endpoint_url' => $mainSource?->source_url,
                    'access_token' => $plainSecret,
                    'public_token' => $token,
                    'marketing_source_id' => $landing->marketing_source_id,
                    'landing_connection_id' => $landing->id,
                    'marketer_user_id' => $landing->created_by_user_id ?: $landing->marketer_user_id,
                    'product_id' => $productId,
                    'ad_channel' => $landing->ad_channel,
                    'sale_priority' => $landing->allocation_method ?: 'round_robin',
                    'manual_import' => (bool) $landing->manual_import,
                    'is_approved' => (bool) $landing->is_approved,
                    'is_active' => true,
                    'metadata' => [
                        'connection_type' => $landing->connection_type,
                        'landing_api_url' => $landing->apiBaseUrl(),
                    ],
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]);

                $created[] = $connection;
            }
        });

        return $created;
    }

    public function updateFlags(PartnerConnection $connection, array $flags, User $actor): PartnerConnection
    {
        $payload = ['updated_by_user_id' => $actor->id];

        if (array_key_exists('manual_import', $flags)) {
            $payload['manual_import'] = (bool) $flags['manual_import'];
        }
        if (array_key_exists('is_approved', $flags)) {
            $payload['is_approved'] = (bool) $flags['is_approved'];
        }
        if (array_key_exists('is_active', $flags)) {
            $payload['is_active'] = (bool) $flags['is_active'];
        }

        $connection->fill($payload)->save();

        return $connection->refresh();
    }

    public function destroy(PartnerConnection $connection): void
    {
        $connection->delete();
    }

    /** @return array<string, mixed> */
    public function serializeConnection(PartnerConnection $connection, int $index = 1): array
    {
        $landing = $connection->landingConnection;
        $mainSource = $landing?->sources
            ?->firstWhere('source_type', LandingConnectionSource::TYPE_MAIN)
            ?? $landing?->sources?->first();

        $marketerName = $connection->marketer?->name
            ?: $landing?->createdBy?->name
            ?: $landing?->marketer?->name;
        $marketerEmail = $connection->marketer?->email
            ?: $landing?->createdBy?->email
            ?: $landing?->marketer?->email;

        $productName = $connection->product?->name
            ?: $landing?->products?->map(fn ($row) => $row->product?->name)->filter()->implode(', ');

        $connectionType = (string) ($connection->metadata['connection_type'] ?? $landing?->connection_type ?? '');
        $adChannel = (string) ($connection->ad_channel ?: $landing?->ad_channel ?: '');
        $sourceName = $connection->source?->name ?: $landing?->name ?: $connection->name;
        $sourceUrl = $mainSource?->source_url ?: $connection->endpoint_url;
        $webhookUrl = $this->webhookUrl($connection);
        $token = (string) ($connection->public_token ?: '');
        $maskedToken = $token === ''
            ? $this->maskSecret((string) $connection->access_token)
            : $token;

        return [
            'id' => $connection->id,
            'index' => $index,
            'partner_type' => $connection->partner_type,
            'landing_connection_id' => $connection->landing_connection_id,
            'marketer' => $marketerName,
            'marketer_email' => $marketerEmail,
            'source' => $sourceName,
            'url' => $sourceUrl,
            'connection_type' => $connectionType,
            'channel' => $adChannel,
            'product' => $productName,
            'sale_priority' => $connection->sale_priority ?: '—',
            'token' => $maskedToken,
            'webhook_url' => $webhookUrl,
            'manual_import' => (bool) $connection->manual_import,
            'approved' => (bool) $connection->is_approved,
            'is_active' => (bool) $connection->is_active,
            'updated_by' => $connection->updater?->name ?: $connection->updater?->email,
            'updated_at' => $connection->updated_at?->format('d / m / Y H:i'),
            'updated_at_iso' => $connection->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Eligible landing connections for the attach dialog.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public function eligibleLandings(string $partnerSlug, array $filters = [], int $perPage = 10): array
    {
        $attachedIds = PartnerConnection::query()
            ->where('partner_type', $partnerSlug)
            ->whereNotNull('landing_connection_id')
            ->pluck('landing_connection_id')
            ->all();

        $query = LandingConnection::query()
            ->with([
                'sources',
                'products.product:id,name',
                'createdBy:id,name,email',
                'marketer:id,name,email',
                'updatedBy:id,name,email',
                'sales.user:id,name',
            ])
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $keyword = trim((string) $filters['search']);
                $query->where(function ($query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('sources', fn ($source) => $source->where('source_url', 'like', "%{$keyword}%"))
                        ->orWhereHas('createdBy', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%"))
                        ->orWhereHas('marketer', fn ($user) => $user->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%"));
                });
            })
            ->when(($filters['marketer_user_id'] ?? '') !== '', function ($query) use ($filters): void {
                $marketerId = (int) $filters['marketer_user_id'];
                $query->where(function ($query) use ($marketerId): void {
                    $query->where('created_by_user_id', $marketerId)
                        ->orWhere('marketer_user_id', $marketerId);
                });
            })
            ->when(($filters['product_id'] ?? '') !== '', fn ($query) => $query->whereHas(
                'products',
                fn ($product) => $product->where('product_id', (int) $filters['product_id']),
            ))
            ->when(($filters['connection_type'] ?? '') !== '', fn ($query) => $query->where(
                'connection_type',
                (string) $filters['connection_type'],
            ))
            ->latest('id');

        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $paginator = $query->paginate(max(10, min(100, $perPage)), ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->values()
                ->map(function (LandingConnection $landing, int $index) use ($attachedIds, $paginator): array {
                    $mainSource = $landing->sources
                        ->firstWhere('source_type', LandingConnectionSource::TYPE_MAIN)
                        ?? $landing->sources->first();
                    $marketer = $landing->createdBy ?: $landing->marketer;
                    $saleNames = $landing->sales
                        ->sortBy('priority')
                        ->map(fn ($sale) => $sale->user?->name)
                        ->filter()
                        ->values()
                        ->all();

                    return [
                        'id' => $landing->id,
                        'index' => (($paginator->firstItem() ?? 1) + $index - 1),
                        'already_attached' => in_array($landing->id, $attachedIds, true),
                        'marketer' => $marketer?->name,
                        'marketer_email' => $marketer?->email,
                        'source' => $landing->name,
                        'url' => $mainSource?->source_url,
                        'connection_type' => $landing->connection_type,
                        'channel' => $landing->ad_channel,
                        'product' => $landing->products->map(fn ($row) => $row->product?->name)->filter()->implode(', '),
                        'sale_priority' => $saleNames === [] ? '' : implode(', ', $saleNames),
                        'webhook_url' => $landing->apiBaseUrl(),
                        'manual_import' => (bool) $landing->manual_import,
                        'approved' => (bool) $landing->is_approved,
                        'updated_by' => $landing->updatedBy?->name ?: $landing->updatedBy?->email,
                        'updated_at' => $landing->updated_at?->format('d / m / Y H:i'),
                    ];
                })
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function webhookUrl(PartnerConnection $connection): string
    {
        $fromMeta = (string) data_get($connection->metadata, 'landing_api_url', '');
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        if ($connection->landingConnection) {
            return $connection->landingConnection->apiBaseUrl();
        }

        $token = (string) ($connection->public_token ?: $connection->id);

        return url('/api/v1/webhooks/'.$connection->partner_type.'/'.$token);
    }

    private function makePublicToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (
            Schema::hasColumn('partner_connections', 'public_token')
            && PartnerConnection::query()->withoutGlobalScopes()->where('public_token', $token)->exists()
        );

        return $token;
    }

    private function maskSecret(string $token): string
    {
        if ($token === '') {
            return '';
        }

        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 4).str_repeat('*', max(4, strlen($token) - 8)).substr($token, -4);
    }
}
