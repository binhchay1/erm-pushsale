<?php

namespace App\Services\Marketing;

use App\Enums\CampaignLeadAllocation;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Models\Company;
use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSale;
use App\Models\LandingConnectionSource;
use App\Models\MarketingSource;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\TenantManager;
use Illuminate\Support\Str;

class LandingConnectionManager
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): LandingConnection
    {
        return DB::transaction(function () use ($data, $actor): LandingConnection {
            // Luồng mới: tạo kết nối landing trước, chưa tạo campaign/marketing_sources nếu chưa duyệt.
            // Đây là điểm cắt với flow cũ để không còn lỗi DB vì product_id/ngân sách/sản phẩm chưa có.
            $payload = [
                ...$this->connectionPayload($data, $actor),
                'marketing_source_id' => null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ];

            $connection = LandingConnection::query()->create($this->onlyExistingColumns('landing_connections', $payload));

            $this->syncChildren($connection, $this->pendingPayload($data));

            if ($this->shouldPublishMarketingSource($data, $connection)) {
                $this->syncMarketingSource($connection->fresh($this->relations()), $actor);
            }

            return $connection->fresh($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function update(LandingConnection $connection, array $data, User $actor): LandingConnection
    {
        return DB::transaction(function () use ($connection, $data, $actor): LandingConnection {
            $payload = [
                ...$this->connectionPayload($data, $actor, $connection),
                'updated_by_user_id' => $actor->id,
            ];

            $connection->update($this->onlyExistingColumns('landing_connections', $payload));

            $this->syncChildren($connection, $this->pendingPayload($data));

            $connection = $connection->fresh($this->relations());
            if ($this->shouldPublishMarketingSource($data, $connection)) {
                $this->syncMarketingSource($connection, $actor);
            } elseif ($connection->marketingSource) {
                $connection->marketingSource->forceFill($this->onlyExistingMarketingSourceColumns([
                    'name' => $connection->name,
                    'is_active' => (bool) $connection->is_active,
                    'is_approved' => false,
                    'approved_by_user_id' => null,
                    'approved_at' => null,
                    'rejected_by_user_id' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]))->save();
            }

            return $connection->fresh($this->relations());
        });
    }

    /**
     * Đồng bộ campaign legacy chỉ ở bước duyệt, sau khi đã có sản phẩm/gói.
     * @param array<string, mixed> $options
     */
    public function approve(LandingConnection $connection, array $options, User $actor): LandingConnection
    {
        return DB::transaction(function () use ($connection, $options, $actor): LandingConnection {
            $metadata = (array) ($connection->metadata ?? []);
            unset(
                $metadata['rejected_at'],
                $metadata['rejected_by_user_id'],
                $metadata['rejection_reason'],
            );

            $connection->update($this->onlyExistingColumns('landing_connections', [
                'budget_type' => (string) ($options['budget_type'] ?? $connection->budget_type ?: 'total'),
                'budget_amount' => max(0, (int) ($options['budget_amount'] ?? $connection->budget_amount ?? 0)),
                'budget_start_date' => filled($options['budget_start_date'] ?? null) ? (string) $options['budget_start_date'] : null,
                'budget_end_date' => filled($options['budget_end_date'] ?? null) ? (string) $options['budget_end_date'] : null,
                'is_approved' => true,
                'is_active' => true,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'updated_by_user_id' => $actor->id,
                'metadata' => $metadata,
            ]));

            $campaign = $this->syncMarketingSource($connection->fresh($this->relations()), $actor);
            $campaign->forceFill([
                'is_approved' => true,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'is_active' => true,
            ])->save();

            return $connection->fresh($this->relations());
        });
    }

    public function reject(LandingConnection $connection, string $reason, User $actor): LandingConnection
    {
        return DB::transaction(function () use ($connection, $reason, $actor): LandingConnection {
            $metadata = (array) ($connection->metadata ?? []);
            $metadata['rejected_at'] = now()->toISOString();
            $metadata['rejected_by_user_id'] = $actor->id;
            $metadata['rejection_reason'] = $reason;
            unset($metadata['approved_at'], $metadata['approved_by_user_id']);

            $connection->update($this->onlyExistingColumns('landing_connections', [
                'is_approved' => false,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'metadata' => $metadata,
                'updated_by_user_id' => $actor->id,
            ]));

            $connection->marketingSource?->update([
                'is_approved' => false,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $connection->fresh($this->relations());
        });
    }

    public function setApprovalFlag(LandingConnection $connection, bool $approved, User $actor): LandingConnection
    {
        if ($approved) {
            return $this->approve($connection, [
                'budget_type' => (string) ($connection->budget_type ?: 'total'),
                'budget_amount' => max(0, (int) $connection->budget_amount),
                'budget_start_date' => $connection->budget_start_date?->toDateString(),
                'budget_end_date' => $connection->budget_end_date?->toDateString(),
            ], $actor);
        }

        return DB::transaction(function () use ($connection, $actor): LandingConnection {
            $metadata = (array) ($connection->metadata ?? []);
            unset(
                $metadata['rejected_at'],
                $metadata['rejected_by_user_id'],
                $metadata['rejection_reason'],
            );
            $metadata['pending_approval_flow'] = true;
            $metadata['request_approval'] = true;

            $connection->update($this->onlyExistingColumns('landing_connections', [
                'is_approved' => false,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'updated_by_user_id' => $actor->id,
                'metadata' => $metadata,
            ]));

            if ($connection->marketingSource) {
                $connection->marketingSource->forceFill($this->onlyExistingMarketingSourceColumns([
                    'is_approved' => false,
                    'approved_by_user_id' => null,
                    'approved_at' => null,
                    'rejected_by_user_id' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]))->save();
            }

            return $connection->fresh($this->relations());
        });
    }

    public function delete(LandingConnection $connection): void
    {
        DB::transaction(function () use ($connection): void {
            $source = $connection->marketingSource;

            $connection->sources()->update(['is_active' => false]);
            $connection->forceFill(['is_active' => false])->save();

            if ($source) {
                $source->forceFill($this->onlyExistingMarketingSourceColumns([
                    'is_active' => false,
                    'is_approved' => false,
                    'name' => str_starts_with((string) $source->name, '[Đã xóa] ')
                        ? $source->name
                        : '[Đã xóa] '.$source->name,
                ]))->save();
            }

            $connection->delete();
        });
    }

    /** @return list<string> */
    public function relations(): array
    {
        return [
            'marketer:id,name,email',
            'marketingSource:id,name,webhook_token,contacts,budget,is_active,is_approved,approved_by_user_id,approved_at,rejected_by_user_id,rejected_at,rejection_reason,product_id',
            'sources',
            'products.product:id,name,sku,unit_price,type',
            'products.source:id,name',
            'sales.user:id,name,email,team_id',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
            'approver:id,name,email',
        ];
    }

    /** @param array<string, mixed> $data */
    private function pendingPayload(array $data): array
    {
        // Bảo vệ flow mới: product/budget duyệt riêng; form tạo nguồn không tự publish.
        if (empty($data['products'])) {
            $data['products'] = [];
        }
        $data['is_approved'] = (bool) ($data['is_approved'] ?? false);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function shouldPublishMarketingSource(array $data, LandingConnection $connection): bool
    {
        // Duyệt được dù chưa gắn sản phẩm — webhook/lead vẫn nhận payload (v128/v130).
        return (bool) ($data['is_approved'] ?? false) || (bool) $connection->is_approved;
    }

    /** @param array<string, mixed> $data */
    private function syncChildren(LandingConnection $connection, array $data): void
    {
        $companyId = (int) $connection->company_id;
        $keptSourceIds = [];
        $sourceMap = [];

        foreach (array_values((array) ($data['sources'] ?? [])) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $source = null;
            if (! empty($row['id'])) {
                $source = $connection->sources()->whereKey((int) $row['id'])->first();
            }

            $payload = [
                'company_id' => $companyId,
                'name' => trim((string) ($row['name'] ?? $connection->name)),
                'source_type' => (string) ($row['source_type'] ?? LandingConnectionSource::TYPE_MAIN),
                'source_url' => trim((string) ($row['source_url'] ?? '')),
                'redirect_url' => filled($row['redirect_url'] ?? null) ? trim((string) $row['redirect_url']) : null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'metadata' => array_filter([
                    'client_key' => $row['client_key'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]),
            ];

            $payload = $this->onlyExistingColumns('landing_connection_sources', $payload);

            if ($source) {
                $source->update($payload);
            } else {
                $source = $connection->sources()->create($payload);
            }

            $keptSourceIds[] = $source->id;
            $sourceMap[(string) ($row['client_key'] ?? $source->id)] = $source->id;
            $sourceMap[(string) $source->id] = $source->id;
        }

        $retiredSourceIds = $connection->sources()
            ->whereNotIn('id', $keptSourceIds ?: [0])
            ->pluck('id');

        if ($retiredSourceIds->isNotEmpty()) {
            $connection->sources()->whereKey($retiredSourceIds->all())->update(['is_active' => false]);
            $connection->sources()->whereKey($retiredSourceIds->all())->delete();
        }

        if (array_key_exists('products', $data) && empty($data['preserve_product_mappings'])) {
            $connection->products()->delete();
            foreach (array_values((array) ($data['products'] ?? [])) as $index => $row) {
                if (! is_array($row) || empty($row['product_id'])) {
                    continue;
                }

                $sourceKey = (string) ($row['source_key'] ?? '');
                LandingConnectionProduct::query()->create($this->onlyExistingColumns('landing_connection_products', [
                    'company_id' => $companyId,
                    'landing_connection_id' => $connection->id,
                    'landing_connection_source_id' => $sourceKey !== '' ? ($sourceMap[$sourceKey] ?? null) : null,
                    'product_id' => (int) $row['product_id'],
                    'item_type' => (string) ($row['item_type'] ?? 'product'),
                    'external_field' => filled($row['external_field'] ?? null) ? trim((string) $row['external_field']) : null,
                    'external_value' => filled($row['external_value'] ?? null) ? trim((string) $row['external_value']) : null,
                    'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                    'unit_price_override' => filled($row['unit_price_override'] ?? null) ? max(0, (int) $row['unit_price_override']) : null,
                    'is_default' => (bool) ($row['is_default'] ?? false),
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                ]));
            }
        }

        if (array_key_exists('sale_user_ids', $data)) {
            $connection->sales()->delete();
            foreach (array_values(array_unique(array_map('intval', (array) ($data['sale_user_ids'] ?? [])))) as $index => $userId) {
                if ($userId <= 0) {
                    continue;
                }

                LandingConnectionSale::query()->create($this->onlyExistingColumns('landing_connection_sales', [
                    'company_id' => $companyId,
                    'landing_connection_id' => $connection->id,
                    'user_id' => $userId,
                    'priority' => $index + 1,
                    'weight' => 1,
                    'is_active' => true,
                ]));
            }
        }
    }

    private function syncMarketingSource(LandingConnection $connection, User $actor): MarketingSource
    {
        $connection->loadMissing(['products.product', 'marketingSource']);
        $firstProductId = $connection->products->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->first() ?: null;

        $campaign = $connection->marketingSource;
        $budgetTotal = $this->budgetTotal($connection);
        $payload = [
            'company_id' => $connection->company_id,
            'name' => $connection->name,
            'marketer_user_id' => $connection->marketer_user_id,
            'created_by_user_id' => $campaign?->created_by_user_id ?: ($connection->created_by_user_id ?: $actor->id),
            'ad_channel' => $connection->ad_channel ?: 'landing',
            'utm_source' => 'landing_connection',
            'utm_campaign' => Str::slug((string) $connection->name) ?: 'landing-connection',
            'budget' => $budgetTotal,
            'is_active' => (bool) $connection->is_active,
            'is_approved' => (bool) $connection->is_approved,
            'lead_allocation' => $this->legacyAllocation($connection->allocation_method),
            'js_tracking_enabled' => false,
            'approved_by_user_id' => $connection->is_approved ? ($connection->approved_by_user_id ?: $actor->id) : null,
            'approved_at' => $connection->is_approved ? ($connection->approved_at ?: now()) : null,
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ];

        // Only touch product_id when mapped — avoids NOT NULL failures on older schemas.
        if ($firstProductId) {
            $payload['product_id'] = $firstProductId;
        }

        $payload = $this->onlyExistingMarketingSourceColumns($payload);

        if ($campaign) {
            $campaign->forceFill($payload)->save();
        } else {
            $campaign = new MarketingSource;
            $campaign->forceFill($payload)->save();
            $connection->forceFill(['marketing_source_id' => $campaign->id])->save();
        }

        return $campaign->fresh();
    }

    private function legacyAllocation(?string $allocation): string
    {
        return match ($allocation ?: 'inherit') {
            'manual' => CampaignLeadAllocation::Manual->value,
            'round_robin', 'priority' => CampaignLeadAllocation::Auto->value,
            default => CampaignLeadAllocation::Inherit->value,
        };
    }

    private function budgetTotal(LandingConnection $connection): int
    {
        $amount = max(0, (int) $connection->budget_amount);
        if ($connection->budget_type !== 'daily' || ! $connection->budget_start_date || ! $connection->budget_end_date) {
            return $amount;
        }

        return $amount * ($connection->budget_start_date->diffInDays($connection->budget_end_date) + 1);
    }


    /** @param array<string, mixed> $payload */
    private function onlyExistingColumns(string $table, array $payload): array
    {
        if (! Schema::hasTable($table)) {
            return $payload;
        }

        return array_filter(
            $payload,
            static fn ($value, string $key): bool => Schema::hasColumn($table, $key),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function companyIdForActor(User $actor): int
    {
        $candidate = (int) ($actor->company_id ?? 0);
        if ($candidate > 0) {
            return $candidate;
        }

        $tenantId = (int) (app(TenantManager::class)->id() ?? 0);
        if ($tenantId > 0) {
            return $tenantId;
        }

        return (int) (Company::query()->orderBy('id')->value('id') ?? 0);
    }

    /** @param array<string, mixed> $payload */
    private function onlyExistingMarketingSourceColumns(array $payload): array
    {
        if (! Schema::hasTable('marketing_sources')) {
            return $payload;
        }

        return array_filter(
            $payload,
            static fn ($value, string $key): bool => Schema::hasColumn('marketing_sources', $key),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** @param array<string, mixed> $data */
    private function connectionPayload(array $data, User $actor, ?LandingConnection $existing = null): array
    {
        $canApprove = $actor->isAdmin()
            || $actor->isPlatformAdmin()
            || $actor->allows(PermissionArea::Marketing, PermissionLevel::Full)
            || $actor->allows(PermissionArea::Integrations, PermissionLevel::Full);
        $approved = $canApprove
            ? (bool) ($data['is_approved'] ?? false)
            : (bool) ($existing?->is_approved ?? false);

        return [
            'company_id' => $this->companyIdForActor($actor),
            'name' => trim((string) $data['name']),
            'marketer_user_id' => (int) $data['marketer_user_id'],
            'connection_type' => (string) ($data['connection_type'] ?? 'landing'),
            'ad_channel' => filled($data['ad_channel'] ?? null) ? trim((string) $data['ad_channel']) : null,
            'allocation_method' => (string) ($data['allocation_method'] ?? 'inherit'),
            'budget_type' => (string) ($data['budget_type'] ?? 'total'),
            'budget_amount' => max(0, (int) ($data['budget_amount'] ?? 0)),
            'budget_start_date' => filled($data['budget_start_date'] ?? null) ? $data['budget_start_date'] : null,
            'budget_end_date' => filled($data['budget_end_date'] ?? null) ? $data['budget_end_date'] : null,
            'success_url' => filled($data['success_url'] ?? null) ? trim((string) $data['success_url']) : null,
            // Tick Nhập TC = cho phép nhập data tay; mặc định tắt giống Pushsale.
            'manual_import' => (bool) ($data['manual_import'] ?? false),
            'is_approved' => $approved,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'approved_by_user_id' => $approved ? ($existing?->approved_by_user_id ?: $actor->id) : null,
            'approved_at' => $approved ? ($existing?->approved_at ?: now()) : null,
            'metadata' => array_filter([
                'version' => 2,
                'notes' => $data['notes'] ?? null,
                'pending_approval_flow' => true,
                'request_approval' => true,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];
    }
}
