<?php

namespace App\Services\Marketing;

use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSale;
use App\Models\LandingConnectionSource;
use App\Models\MarketingSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LandingConnectionManager
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): LandingConnection
    {
        return DB::transaction(function () use ($data, $actor): LandingConnection {
            $marketingSource = MarketingSource::query()->create($this->marketingSourcePayload($data, $actor));

            $connection = LandingConnection::query()->create([
                ...$this->connectionPayload($data, $actor),
                'marketing_source_id' => $marketingSource->id,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->syncChildren($connection, $data);

            return $connection->fresh($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function update(LandingConnection $connection, array $data, User $actor): LandingConnection
    {
        return DB::transaction(function () use ($connection, $data, $actor): LandingConnection {
            $connection->update([
                ...$this->connectionPayload($data, $actor, $connection),
                'updated_by_user_id' => $actor->id,
            ]);

            $source = $connection->marketingSource;
            if ($source) {
                $source->update($this->marketingSourcePayload($data, $actor, $source));
            }

            $this->syncChildren($connection, $data);

            return $connection->fresh($this->relations());
        });
    }

    public function delete(LandingConnection $connection): void
    {
        DB::transaction(function () use ($connection): void {
            $source = $connection->marketingSource;

            $connection->sources()->update(['is_active' => false]);
            $connection->forceFill(['is_active' => false])->save();
            $source?->update(['is_active' => false]);
            $connection->delete();
        });
    }

    /** @return list<string> */
    public function relations(): array
    {
        return [
            'marketer:id,name',
            'marketingSource:id,name,webhook_token,contacts,budget,is_active,is_approved',
            'sources',
            'products.product:id,name,sku,unit_price',
            'products.source:id,name',
            'sales.user:id,name,team_id',
        ];
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
                'name' => trim((string) ($row['name'] ?? '')),
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

        $connection->products()->delete();
        foreach (array_values((array) ($data['products'] ?? [])) as $index => $row) {
            if (! is_array($row) || empty($row['product_id'])) {
                continue;
            }

            $sourceKey = (string) ($row['source_key'] ?? '');
            LandingConnectionProduct::query()->create([
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
            ]);
        }

        $connection->sales()->delete();
        foreach (array_values(array_unique(array_map('intval', (array) ($data['sale_user_ids'] ?? [])))) as $index => $userId) {
            if ($userId <= 0) {
                continue;
            }

            LandingConnectionSale::query()->create([
                'company_id' => $companyId,
                'landing_connection_id' => $connection->id,
                'user_id' => $userId,
                'priority' => $index + 1,
                'weight' => 1,
                'is_active' => true,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function connectionPayload(array $data, User $actor, ?LandingConnection $existing = null): array
    {
        $canApprove = $actor->isAdmin();
        $approved = $canApprove
            ? (bool) ($data['is_approved'] ?? false)
            : (bool) ($existing?->is_approved ?? false);

        return [
            'company_id' => $actor->company_id,
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
            'manual_import' => (bool) ($data['manual_import'] ?? false),
            'is_approved' => $approved,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'approved_by_user_id' => $approved ? ($existing?->approved_by_user_id ?: $actor->id) : null,
            'approved_at' => $approved ? ($existing?->approved_at ?: now()) : null,
            'metadata' => array_filter([
                'version' => 1,
                'notes' => $data['notes'] ?? null,
            ]),
        ];
    }

    /** @param array<string, mixed> $data */
    private function marketingSourcePayload(array $data, User $actor, ?MarketingSource $existing = null): array
    {
        $productId = collect((array) ($data['products'] ?? []))
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->first();

        $allocation = match ((string) ($data['allocation_method'] ?? 'inherit')) {
            'manual' => 'manual',
            'round_robin', 'priority' => 'auto',
            default => 'inherit',
        };

        $approved = $actor->isAdmin()
            ? (bool) ($data['is_approved'] ?? false)
            : (bool) ($existing?->is_approved ?? false);

        $budgetAmount = max(0, (int) ($data['budget_amount'] ?? 0));
        $budgetTotal = $budgetAmount;
        if (($data['budget_type'] ?? 'total') === 'daily'
            && filled($data['budget_start_date'] ?? null)
            && filled($data['budget_end_date'] ?? null)) {
            $start = \Illuminate\Support\Carbon::parse((string) $data['budget_start_date'])->startOfDay();
            $end = \Illuminate\Support\Carbon::parse((string) $data['budget_end_date'])->startOfDay();
            $budgetTotal = $budgetAmount * ($start->diffInDays($end) + 1);
        }

        return [
            'name' => trim((string) $data['name']),
            'product_id' => $productId,
            'marketer_user_id' => (int) $data['marketer_user_id'],
            'created_by_user_id' => $existing?->created_by_user_id ?: $actor->id,
            'ad_channel' => filled($data['ad_channel'] ?? null) ? trim((string) $data['ad_channel']) : 'landing',
            'utm_source' => 'landing_connection',
            'utm_campaign' => Str::slug((string) $data['name']) ?: 'landing-connection',
            'budget' => $budgetTotal,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_approved' => $approved,
            'lead_allocation' => $allocation,
            'js_tracking_enabled' => false,
            'approved_by_user_id' => $approved ? ($existing?->approved_by_user_id ?: $actor->id) : null,
            'approved_at' => $approved ? ($existing?->approved_at ?: now()) : null,
        ];
    }
}
