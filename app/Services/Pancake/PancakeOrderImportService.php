<?php

namespace App\Services\Pancake;

use App\Enums\IntegrationPlatform;
use App\Enums\PancakeAssignmentMode;
use App\Integrations\Pancake\PancakeLeadDriver;
use App\Models\IntegrationConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\PancakeSyncRecord;
use App\Models\User;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PancakeOrderImportService
{
    public function __construct(
        protected LeadIngestionService $leadIngestion,
        protected PancakeConnectionResolver $connections,
        protected PancakeAssignmentResolver $assignmentResolver,
    ) {}

    /**
     * Import 1 đơn/lead Pancake.
     *
     * Luồng extension: $actor là user đang đăng nhập SaleOps, nên backend gán về
     * chính sale đó hoặc sale được chọn sau khi kiểm quyền. Không tin sale_user_id
     * từ frontend nếu user không có quyền chỉ định.
     *
     * Luồng webhook/polling: $actor = null, chỉ gán theo mapping Pancake user,
     * owner hội thoại cũ, hoặc để LeadIngestionService chia tự động.
     *
     * @param  array<string, mixed>  $payload
     * @return array{lead: LeadIngestion, order: ?Order, sync_record: PancakeSyncRecord, assignment: array<string, mixed>}
     */
    public function import(array $payload, ?User $actor = null, ?User $forceSale = null): array
    {
        $connection = $this->connections->connection();
        $driver = new PancakeLeadDriver;
        $normalized = $driver->normalize($payload);

        $this->assertConnectionScope($payload, $normalized, $connection);

        $campaign = $this->resolveSource($payload, $normalized, $connection);
        $assignment = $forceSale
            ? [
                'sale' => $forceSale,
                'mode' => PancakeAssignmentMode::SelectedSale->value,
                'reason' => __('integrations.pancake_assignment.forced_by_service'),
                'requested_sale_id' => $forceSale->id,
                'pancake_user_key' => null,
                'source' => $actor ? 'extension' : 'service',
            ]
            : $this->assignmentResolver->resolve($payload, $normalized, $connection, $actor);

        /** @var User|null $sale */
        $sale = $assignment['sale'] ?? null;
        $forcePending = ($assignment['mode'] ?? null) === PancakeAssignmentMode::PendingPool->value;

        $lead = DB::transaction(function () use ($driver, $payload, $campaign, $sale, $forcePending) {
            return $this->leadIngestion->ingestWithForceSale($driver, $payload, $sale, $campaign, $forcePending);
        });

        $lead->load('order.items', 'order.saleUser', 'order.marketingSource');
        $order = $lead->order;
        $syncRecord = $this->upsertSyncRecord($connection, $payload, $normalized, $lead, $order, $actor, $assignment);

        return [
            'lead' => $lead,
            'order' => $order,
            'sync_record' => $syncRecord,
            'assignment' => $this->presentAssignment($assignment),
        ];
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    public function resolveSource(array $payload, array $normalized, IntegrationConnection $connection): ?MarketingSource
    {
        $credentials = $connection->credentials ?? [];
        $sourceId = Arr::get($payload, 'marketing_source_id')
            ?? Arr::get($payload, 'saleops.marketing_source_id')
            ?? Arr::get($credentials, 'default_marketing_source_id')
            ?? config('integrations.platforms.pancake.fields.default_marketing_source_id.default');

        if (filled($sourceId)) {
            $source = MarketingSource::query()->find((int) $sourceId);
            if ($source) {
                return $source;
            }
        }

        $pancake = $normalized['pancake'] ?? [];
        $campaignKey = $normalized['utm_campaign'] ?? null;
        $sourceLabel = $pancake['source_label']
            ?? Arr::get($payload, 'source_name')
            ?? Arr::get($payload, 'page_name')
            ?? Arr::get($payload, 'shop_name')
            ?? $campaignKey
            ?? 'Pancake POS';

        $query = MarketingSource::query()
            ->where('ad_channel', IntegrationPlatform::Pancake->value);

        if (filled($campaignKey)) {
            $query->where('utm_campaign', (string) $campaignKey);
        } else {
            $query->where('name', 'Pancake — '.$sourceLabel);
        }

        $existing = $query->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        return MarketingSource::query()->create([
            'name' => 'Pancake — '.$sourceLabel,
            'ad_channel' => IntegrationPlatform::Pancake->value,
            'utm_source' => $normalized['utm_source'] ?? 'pancake',
            'utm_campaign' => filled($campaignKey) ? (string) $campaignKey : Str::of((string) $sourceLabel)->slug()->value(),
            'is_active' => true,
            'is_approved' => true,
        ]);
    }

    /**
     * Backward-compatible public method. New code should use PancakeAssignmentResolver.
     *
     * @param array<string, mixed> $payload
     */
    public function resolveSale(array $payload, ?User $actor = null): ?User
    {
        $driver = new PancakeLeadDriver;
        $connection = $this->connections->connection();
        $decision = $this->assignmentResolver->resolve($payload, $driver->normalize($payload), $connection, $actor);

        return $decision['sale'] ?? null;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    protected function upsertSyncRecord(
        IntegrationConnection $connection,
        array $payload,
        array $normalized,
        LeadIngestion $lead,
        ?Order $order,
        ?User $actor,
        array $assignment,
    ): PancakeSyncRecord {
        $pancake = $normalized['pancake'] ?? [];
        $externalId = (string) ($pancake['order_id']
            ?? Arr::get($payload, 'pancake_order_id')
            ?? Arr::get($payload, 'order_id')
            ?? Arr::get($payload, 'id')
            ?? $normalized['external_id']);

        $metadata = [
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'page_id' => $pancake['page_id'] ?? Arr::get($payload, 'page_id'),
            'conversation_id' => $pancake['conversation_id'] ?? Arr::get($payload, 'conversation_id'),
            'customer_id' => $pancake['customer_id'] ?? Arr::get($payload, 'customer_id'),
            'pancake_user_id' => $pancake['pancake_user_id'] ?? Arr::get($payload, 'pancake_user_id') ?? Arr::get($payload, 'assignee.id'),
            'pancake_user_email' => $pancake['pancake_user_email'] ?? Arr::get($payload, 'pancake_user_email') ?? Arr::get($payload, 'assignee.email'),
            'assignment' => $this->presentAssignment($assignment),
        ];

        return PancakeSyncRecord::query()->updateOrCreate(
            [
                'company_id' => $connection->company_id,
                'external_type' => PancakeSyncRecord::TYPE_ORDER,
                'external_id' => $externalId,
            ],
            [
                'integration_connection_id' => $connection->id,
                'shop_id' => (string) ($pancake['shop_id'] ?? Arr::get($payload, 'shop_id') ?? ($connection->credentials['shop_id'] ?? '')) ?: null,
                'external_code' => Arr::get($payload, 'order_code') ?? Arr::get($payload, 'code'),
                'lead_ingestion_id' => $lead->id,
                'order_id' => $order?->id,
                'status' => $order ? 'order_created' : (string) $lead->status->value,
                'payload' => $payload,
                'metadata' => $metadata,
                'last_synced_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $assignment @return array<string, mixed> */
    protected function presentAssignment(array $assignment): array
    {
        $sale = $assignment['sale'] ?? null;

        return [
            'mode' => $assignment['mode'] ?? PancakeAssignmentMode::AutoRouting->value,
            'reason' => $assignment['reason'] ?? null,
            'source' => $assignment['source'] ?? null,
            'requested_sale_id' => $assignment['requested_sale_id'] ?? null,
            'pancake_user_key' => $assignment['pancake_user_key'] ?? null,
            'sale_user' => $sale instanceof User ? $sale->only(['id', 'name', 'email']) : null,
        ];
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    protected function assertConnectionScope(array $payload, array $normalized, IntegrationConnection $connection): void
    {
        $credentials = $connection->credentials ?? [];
        $pancake = is_array($normalized['pancake'] ?? null) ? $normalized['pancake'] : [];
        $shopId = (string) ($pancake['shop_id'] ?? Arr::get($payload, 'shop_id') ?? '');
        $pageId = (string) ($pancake['page_id'] ?? Arr::get($payload, 'page_id') ?? '');

        $allowedShopIds = $this->csvList($credentials['allowed_shop_ids'] ?? config('integrations.platforms.pancake.fields.allowed_shop_ids.default'));
        $configuredShopId = $this->connections->credential($credentials, 'shop_id');
        if ($configuredShopId) {
            $allowedShopIds[] = $configuredShopId;
        }

        $allowedPageIds = $this->csvList($credentials['allowed_page_ids'] ?? config('integrations.platforms.pancake.fields.allowed_page_ids.default'));
        $configuredPageId = $this->connections->credential($credentials, 'page_id');
        if ($configuredPageId) {
            $allowedPageIds[] = $configuredPageId;
        }

        $allowedShopIds = array_values(array_unique(array_filter($allowedShopIds)));
        $allowedPageIds = array_values(array_unique(array_filter($allowedPageIds)));

        if ($shopId !== '' && $allowedShopIds !== [] && ! in_array($shopId, $allowedShopIds, true)) {
            throw ValidationException::withMessages([
                'shop_id' => __('integrations.pancake_assignment.shop_not_allowed'),
            ]);
        }

        if ($pageId !== '' && $allowedPageIds !== [] && ! in_array($pageId, $allowedPageIds, true)) {
            throw ValidationException::withMessages([
                'page_id' => __('integrations.pancake_assignment.page_not_allowed'),
            ]);
        }
    }

    /** @return list<string> */
    protected function csvList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }
}
