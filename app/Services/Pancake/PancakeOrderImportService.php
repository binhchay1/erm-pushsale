<?php

namespace App\Services\Pancake;

use App\Enums\IntegrationPlatform;
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

class PancakeOrderImportService
{
    public function __construct(
        protected LeadIngestionService $leadIngestion,
        protected PancakeConnectionResolver $connections,
    ) {}

    /**
     * Import 1 đơn/lead Pancake ngay lập tức và trả về đơn nội bộ nếu đã được chia cho sale.
     *
     * @param  array<string, mixed>  $payload
     * @return array{lead: LeadIngestion, order: ?Order, sync_record: PancakeSyncRecord}
     */
    public function import(array $payload, ?User $actor = null, ?User $forceSale = null): array
    {
        $connection = $this->connections->connection();
        $driver = new PancakeLeadDriver;
        $normalized = $driver->normalize($payload);
        $campaign = $this->resolveSource($payload, $normalized, $connection);
        $forceSale ??= $this->resolveSale($payload, $actor);

        $lead = DB::transaction(function () use ($driver, $payload, $campaign, $forceSale) {
            return $this->leadIngestion->ingestWithForceSale($driver, $payload, $forceSale, $campaign);
        });

        $lead->load('order.items', 'order.saleUser', 'order.marketingSource');
        $order = $lead->order;
        $syncRecord = $this->upsertSyncRecord($connection, $payload, $normalized, $lead, $order, $actor);

        return [
            'lead' => $lead,
            'order' => $order,
            'sync_record' => $syncRecord,
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

    /** @param array<string, mixed> $payload */
    public function resolveSale(array $payload, ?User $actor = null): ?User
    {
        if ($actor?->isSales()) {
            return $actor;
        }

        $saleId = Arr::get($payload, 'sale_user_id')
            ?? Arr::get($payload, 'saleops.sale_user_id')
            ?? Arr::get($payload, 'assignee.sale_user_id');

        if (filled($saleId)) {
            $sale = User::query()->whereKey((int) $saleId)->first();
            if ($sale?->isSales()) {
                return $sale;
            }
        }

        $email = Arr::get($payload, 'sale_email')
            ?? Arr::get($payload, 'assignee.email')
            ?? Arr::get($payload, 'pancake_user_email');

        if (filled($email)) {
            $sale = User::query()
                ->where('email', (string) $email)
                ->first();
            if ($sale?->isSales()) {
                return $sale;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    protected function upsertSyncRecord(
        IntegrationConnection $connection,
        array $payload,
        array $normalized,
        LeadIngestion $lead,
        ?Order $order,
        ?User $actor,
    ): PancakeSyncRecord {
        $pancake = $normalized['pancake'] ?? [];
        $externalId = (string) ($pancake['order_id']
            ?? Arr::get($payload, 'pancake_order_id')
            ?? Arr::get($payload, 'order_id')
            ?? Arr::get($payload, 'id')
            ?? $normalized['external_id']);

        return PancakeSyncRecord::query()->updateOrCreate(
            [
                'external_type' => PancakeSyncRecord::TYPE_ORDER,
                'external_id' => $externalId,
            ],
            [
                'company_id' => $connection->company_id,
                'integration_connection_id' => $connection->id,
                'shop_id' => (string) ($pancake['shop_id'] ?? Arr::get($payload, 'shop_id') ?? ($connection->credentials['shop_id'] ?? '')) ?: null,
                'external_code' => Arr::get($payload, 'order_code') ?? Arr::get($payload, 'code'),
                'lead_ingestion_id' => $lead->id,
                'order_id' => $order?->id,
                'status' => $order ? 'order_created' : (string) $lead->status->value,
                'payload' => $payload,
                'metadata' => [
                    'actor_user_id' => $actor?->id,
                    'actor_email' => $actor?->email,
                    'page_id' => $pancake['page_id'] ?? Arr::get($payload, 'page_id'),
                    'conversation_id' => $pancake['conversation_id'] ?? null,
                    'customer_id' => $pancake['customer_id'] ?? null,
                ],
                'last_synced_at' => now(),
            ],
        );
    }
}
