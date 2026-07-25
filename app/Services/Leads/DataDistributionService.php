<?php

namespace App\Services\Leads;

use App\Enums\LeadIngestionStatus;
use App\Enums\OperationStage;
use App\Models\LeadIngestion;
use App\Models\Product;
use App\Models\Pushsale\DataDistributionBatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DataDistributionService
{
    public const MAX_BATCH_SIZE = 5000;

    public function __construct(
        private readonly ManualLeadAllocationService $allocator,
    ) {}

    /** @return array<string, mixed> */
    public function indexPayload(User $actor, array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $productRows = $this->productRows($actor, $filters);
        $saleRows = $this->saleRows($actor, $filters);

        return [
            'filters' => $filters,
            'products' => $productRows,
            'sales' => $saleRows,
            'teams' => Team::query()
                ->where('type', 'sale')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Team $team) => ['id' => (string) $team->id, 'name' => $team->name])
                ->values(),
            'operationOptions' => collect(OperationStage::cases())
                ->map(fn (OperationStage $stage) => ['value' => $stage->value, 'label' => $stage->label()])
                ->values(),
            'stats' => [
                'pending' => array_sum(array_column($productRows, 'contact_count')),
                'max_batch_size' => self::MAX_BATCH_SIZE,
                'selected_total' => 0,
                'available_sales' => count(array_filter($saleRows, fn ($row) => (bool) $row['can_receive'])),
            ],
            'lastBatch' => class_exists(DataDistributionBatch::class)
                ? DataDistributionBatch::query()->where('company_id', $actor->company_id)->latest('id')->first()?->only(['id', 'total_contacts', 'allocated_contacts', 'status', 'created_at'])
                : null,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function distribute(User $actor, array $payload): array
    {
        $filters = $this->normalizeFilters((array) ($payload['filters'] ?? []));
        $productAllocations = collect($payload['product_allocations'] ?? [])
            ->map(fn ($row) => [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'quantity' => max(0, min(self::MAX_BATCH_SIZE, (int) ($row['quantity'] ?? 0))),
            ])
            ->filter(fn ($row) => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();

        $saleIds = collect($payload['sale_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($productAllocations->isEmpty()) {
            throw ValidationException::withMessages(['product_allocations' => 'Chọn ít nhất một sản phẩm và nhập số lượng cần phân bổ.']);
        }
        if ($saleIds->isEmpty()) {
            throw ValidationException::withMessages(['sale_user_ids' => 'Chọn ít nhất một Sale nhận data.']);
        }

        $allowedSaleIds = $this->allowedSaleIdsForProducts($productAllocations->pluck('product_id')->map(fn ($id) => (int) $id)->all());
        if (is_array($allowedSaleIds)) {
            $saleIds = $saleIds->intersect($allowedSaleIds)->values();
            if ($saleIds->isEmpty()) {
                throw ValidationException::withMessages(['sale_user_ids' => 'Các Sale đã chọn chưa được phân quyền nhận data của sản phẩm này. Vào Quản lý sản phẩm → Phân quyền để cấu hình lại.']);
            }
        }

        $flags = [
            'delete_operation_history' => (bool) ($payload['delete_operation_history'] ?? false),
            'delete_internal_messages' => (bool) ($payload['delete_internal_messages'] ?? false),
            'hide_sales_not_receiving' => (bool) ($payload['hide_sales_not_receiving'] ?? true),
            'skip_sales_not_receiving' => (bool) ($payload['skip_sales_not_receiving'] ?? true),
            'hide_locked_sales' => (bool) ($payload['hide_locked_sales'] ?? true),
            'skip_locked_sales' => (bool) ($payload['skip_locked_sales'] ?? true),
        ];

        $sales = $this->eligibleSales($actor, $saleIds->all(), $flags)->values();
        if ($sales->isEmpty()) {
            throw ValidationException::withMessages(['sale_user_ids' => 'Không có Sale hợp lệ để nhận data theo điều kiện đã chọn.']);
        }

        $targetTotal = min(self::MAX_BATCH_SIZE, (int) $productAllocations->sum('quantity'));
        $allocated = 0;
        $lineStats = [];
        $batch = $this->createBatch($actor, $filters, $payload, $targetTotal);

        foreach ($productAllocations as $productAllocation) {
            if ($allocated >= self::MAX_BATCH_SIZE) {
                break;
            }

            $limit = min((int) $productAllocation['quantity'], self::MAX_BATCH_SIZE - $allocated);
            $leadIds = $this->leadIdsForProduct($actor, (int) $productAllocation['product_id'], $filters, $limit);
            if ($leadIds === []) {
                $lineStats[] = ['product_id' => $productAllocation['product_id'], 'requested' => $limit, 'allocated' => 0];
                continue;
            }

            $buckets = $this->roundRobinBuckets($leadIds, $sales);
            $productAllocated = 0;
            foreach ($buckets as $saleId => $ids) {
                if ($ids === []) continue;
                $sale = $sales->firstWhere('id', (int) $saleId);
                if (! $sale) continue;
                $count = $this->allocator->allocate($ids, $sale, $actor);
                $allocated += $count;
                $productAllocated += $count;
            }

            $lineStats[] = [
                'product_id' => $productAllocation['product_id'],
                'requested' => $limit,
                'allocated' => $productAllocated,
            ];
        }

        if ($batch) {
            $batch->forceFill([
                'allocated_contacts' => $allocated,
                'status' => $allocated > 0 ? 'completed' : 'empty',
                'completed_at' => now(),
                'line_stats' => $lineStats,
            ])->save();
        }

        return [
            'requested' => $targetTotal,
            'allocated' => $allocated,
            'lines' => $lineStats,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'returning_scope' => Arr::get($filters, 'returning_scope', ''),
            'data_scope' => Arr::get($filters, 'data_scope', 'all'),
            'operation_stage' => Arr::get($filters, 'operation_stage', ''),
            'team_id' => Arr::get($filters, 'team_id', ''),
            'date_from' => Arr::get($filters, 'date_from') ?: now()->subMonth()->toDateString(),
            'date_to' => Arr::get($filters, 'date_to') ?: now()->toDateString(),
        ];
    }


    /**
     * Product permission contract:
     * - sale_user_ids = [] and available_sale = true: all sales may receive data.
     * - sale_user_ids has values: only those sales may receive data.
     * - available_sale = false: product is blocked from sale distribution.
     *
     * @param list<int> $productIds
     * @return list<int>|null null means unrestricted
     */
    private function allowedSaleIdsForProducts(array $productIds): ?array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === [] || ! Schema::hasColumn('products', 'sale_user_ids')) {
            return null;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'available_sale', 'sale_user_ids']);

        $allowed = null;
        foreach ($products as $product) {
            if (! (bool) $product->available_sale) {
                return [];
            }

            $ids = is_array($product->sale_user_ids) ? array_values(array_unique(array_filter(array_map('intval', $product->sale_user_ids)))) : [];
            if ($ids === []) {
                continue;
            }

            $allowed = $allowed === null ? $ids : array_values(array_intersect($allowed, $ids));
        }

        return $allowed;
    }

    /** @return list<array<string, mixed>> */
    private function productRows(User $actor, array $filters): array
    {
        return Product::query()
            ->where('is_active', true)
            ->where('available_sale', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'type'])
            ->map(function (Product $product) use ($actor, $filters): array {
                return [
                    'id' => (string) $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'contact_count' => $this->countLeadsForProduct($actor, $product, $filters),
                    'allocated_quantity' => 0,
                ];
            })
            ->filter(fn (array $row) => (int) $row['contact_count'] > 0)
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function saleRows(User $actor, array $filters): array
    {
        $query = User::query()
            ->with(['team:id,name', 'operationalProfile:id,user_id,receive_data,is_locked'])
            ->where('role', User::ROLE_SALES)
            ->orderBy('team_id')
            ->orderBy('name');

        if ($filters['team_id'] !== '') {
            $query->where('team_id', (int) $filters['team_id']);
        }

        return $query->get(['id', 'name', 'email', 'team_id'])
            ->map(function (User $sale) use ($actor): array {
                $profile = $sale->operationalProfile;
                $activeOrders = DB::table('orders')
                    ->where('sale_user_id', $sale->id)
                    ->whereNull('closed_at')
                    ->count();
                $allocatedToday = DB::table('orders')
                    ->where('sale_user_id', $sale->id)
                    ->whereDate('assigned_at', now()->toDateString())
                    ->count();

                return [
                    'id' => (string) $sale->id,
                    'name' => $sale->name,
                    'email' => $sale->email,
                    'team' => $sale->team?->name,
                    'contact_count' => $allocatedToday,
                    'active_count' => $activeOrders,
                    'receive_data' => $profile?->receive_data !== false,
                    'is_locked' => (bool) ($profile?->is_locked ?? false),
                    'can_receive' => $profile?->receive_data !== false && ! (bool) ($profile?->is_locked ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function eligibleSales(User $actor, array $saleIds, array $flags)
    {
        $query = User::query()
            ->with('operationalProfile:id,user_id,receive_data,is_locked')
            ->whereIn('id', $saleIds)
            ->where('role', User::ROLE_SALES)
            ->orderBy('id');

        $sales = $query->get();

        return $sales->filter(function (User $sale) use ($flags): bool {
            $profile = $sale->operationalProfile;
            if (($flags['skip_sales_not_receiving'] ?? true) && $profile?->receive_data === false) {
                return false;
            }
            if (($flags['skip_locked_sales'] ?? true) && (bool) ($profile?->is_locked ?? false)) {
                return false;
            }
            return true;
        })->values();
    }

    private function countLeadsForProduct(User $actor, Product $product, array $filters): int
    {
        return $this->leadQuery($actor, $filters)
            ->where(function (Builder $query) use ($product) {
                $this->applyProductMatch($query, $product);
            })
            ->count();
    }

    /** @return list<int> */
    private function leadIdsForProduct(User $actor, int $productId, array $filters, int $limit): array
    {
        $product = Product::query()->find($productId);
        if (! $product) return [];

        return $this->leadQuery($actor, $filters)
            ->where(function (Builder $query) use ($product) {
                $this->applyProductMatch($query, $product);
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function leadQuery(User $actor, array $filters): Builder
    {
        $query = LeadIngestion::query()
            ->where('counts_as_lead', true)
            ->where('status', LeadIngestionStatus::Pending)
            ->whereNull('order_id')
            ->whereBetween('created_at', [
                Carbon::parse($filters['date_from'])->startOfDay(),
                Carbon::parse($filters['date_to'])->endOfDay(),
            ]);

        if ($filters['returning_scope'] === 'old') {
            $query->where(function (Builder $q): void {
                $q->where('requires_review', true)->orWhereNotNull('related_order_id');
            });
        } elseif ($filters['returning_scope'] === 'new') {
            $query->where('requires_review', false)->whereNull('related_order_id');
        }

        if ($filters['data_scope'] === 'landing') {
            $query->where('platform', 'landing');
        } elseif ($filters['data_scope'] === 'manual') {
            $query->where('platform', 'manual');
        } elseif ($filters['data_scope'] === 'pancake') {
            $query->where('platform', 'pancake');
        }

        return $query;
    }

    private function applyProductMatch(Builder $query, Product $product): void
    {
        $id = (string) $product->id;
        $name = addcslashes($product->name, '%_');
        $sku = $product->sku ? addcslashes($product->sku, '%_') : null;

        $query->where(function (Builder $q) use ($id, $name, $sku): void {
            $q->where('product_interest', 'like', "%{$name}%")
              ->orWhere('payload', 'like', '%"product_id":'.$id.'%')
              ->orWhere('payload', 'like', '%"product_id":"'.$id.'"%')
              ->orWhere('payload', 'like', '%"id":'.$id.'%')
              ->orWhere('payload', 'like', '%"name":"%'.$name.'%"%');

            if ($sku) {
                $q->orWhere('product_interest', 'like', "%{$sku}%")
                  ->orWhere('payload', 'like', '%'.$sku.'%');
            }
        });
    }

    /** @param list<int> $leadIds @param \Illuminate\Support\Collection<int, User> $sales @return array<int, list<int>> */
    private function roundRobinBuckets(array $leadIds, $sales): array
    {
        $saleIds = $sales->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $buckets = array_fill_keys($saleIds, []);
        foreach (array_values($leadIds) as $index => $leadId) {
            $saleId = $saleIds[$index % count($saleIds)];
            $buckets[$saleId][] = (int) $leadId;
        }
        return $buckets;
    }

    private function createBatch(User $actor, array $filters, array $payload, int $targetTotal): ?DataDistributionBatch
    {
        if (! class_exists(DataDistributionBatch::class) || ! Schema::hasTable('data_distribution_batches')) {
            return null;
        }

        return DataDistributionBatch::query()->create([
            'company_id' => $actor->company_id,
            'created_by_user_id' => $actor->id,
            'filters' => $filters,
            'flags' => Arr::only($payload, [
                'delete_operation_history',
                'delete_internal_messages',
                'hide_sales_not_receiving',
                'skip_sales_not_receiving',
                'hide_locked_sales',
                'skip_locked_sales',
            ]),
            'total_contacts' => $targetTotal,
            'allocated_contacts' => 0,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }
}
