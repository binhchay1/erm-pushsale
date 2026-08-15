<?php

namespace App\Services\Reports\SalesLeader;

use App\Models\SaleOptimizationAlertThreshold;
use App\Models\SaleOptimizationCatalog;
use App\Models\SaleOptimizationLevel;
use App\Models\SaleOptimizationTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class SalesOptimizationReportService
{
    public function __construct(
        private readonly SalesLeaderReportQuery $query,
        private readonly SalesLeaderSaleAggregator $aggregator,
    ) {}

    public function build(Request $request): array
    {
        $thresholds = $this->thresholds($request);
        $targets = $this->targets($request);
        $grouped = $this->aggregator->filterGrouped(
            $this->aggregator->groupBySale($this->query->loadOrders($request), $request),
            $request,
        );

        $workingDays = $this->query->workingDaysInRange($request);
        $monthDays = max(1, (int) now()->daysInMonth);
        $dayFactor = $workingDays / $monthDays;

        $rows = $grouped->map(function (array $row, int $index) use ($thresholds, $targets, $dayFactor): array {
            $unique = max(0, $row['contacts'] - $row['duplicate_contacts']);
            $closeRate = round(($row['closed'] / max(1, $row['contacts'])) * 100, 2);
            $aov = (int) round($row['revenue'] / max(1, $row['closed']));
            $productsPerOrder = round($row['products'] / max(1, $row['closed']), 2);
            $revenuePerContact = (int) round($row['provisional_revenue'] / max(1, max($unique, 1)));
            $saleTarget = (float) ($targets[$row['id']]['close_rate'] ?? $targets[0]['close_rate'] ?? 100);
            // T7/CN: quy đổi chỉ tiêu theo tỷ lệ ngày làm việc trong kỳ.
            $adjustedTarget = round($saleTarget * $dayFactor, 2);
            $ratio = $adjustedTarget > 0 ? round(($closeRate / $adjustedTarget) * 100, 2) : null;

            return [
                'index' => $index + 1,
                'sale_id' => $row['id'],
                'sale' => $row['name'],
                'sale_account' => $row['account'],
                'receive_data' => $row['receive_data'],
                'provisional_revenue' => $row['provisional_revenue'],
                'success_revenue' => $row['delivered_revenue'],
                'contacts' => $row['contacts'],
                'allocated_total' => $row['contacts'],
                'allocated_duplicate' => $row['duplicate_contacts'],
                'allocated_unique' => $unique,
                'call_duration_seconds' => null,
                'avg_call_seconds' => null,
                'closed_contacts' => $row['closed'],
                'close_rate' => $closeRate,
                'close_rate_target' => $adjustedTarget,
                'close_rate_ratio' => $ratio,
                'avg_order_value' => $aov,
                'products_per_order' => $productsPerOrder,
                'untouched' => $row['untouched'],
                'revenue_per_contact' => $revenuePerContact,
                'cancelled_revenue' => $row['cancelled_revenue'],
                'returned_revenue' => $row['returned_revenue'],
                'tone' => $this->tone($ratio, $thresholds),
            ];
        })->values();

        $totals = $this->totals($rows);
        $page = $this->query->paginateRows($rows, $request);

        return [
            'data' => $page['data'],
            'meta' => $page['meta'],
            'summary' => [
                'totals' => $totals,
                'thresholds' => $thresholds,
                'levels' => $this->levels($request),
                'targets' => array_values($targets),
                'target_map' => $targets,
                'catalogs' => $this->catalogs($request),
            ],
        ];
    }

    public function saveThresholds(int $companyId, float $low, float $high): void
    {
        if (! Schema::hasTable('sale_optimization_alert_thresholds')) {
            return;
        }
        SaleOptimizationAlertThreshold::query()->updateOrCreate(
            ['company_id' => $companyId, 'metric_key' => 'close_rate'],
            ['low_ratio' => $low, 'high_ratio' => $high],
        );
    }

    /** @param list<array{sale_user_id?:int|null,metric_key:string,target_value:float|int}> $rows */
    public function saveTargets(int $companyId, array $rows): void
    {
        if (! Schema::hasTable('sale_optimization_targets')) {
            return;
        }
        foreach ($rows as $row) {
            SaleOptimizationTarget::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'sale_user_id' => $row['sale_user_id'] ?? null,
                    'metric_key' => $row['metric_key'],
                ],
                ['target_value' => $row['target_value']],
            );
        }
    }

    /**
     * @param  list<array{id?:int|null,name:string,metrics?:array<string,mixed>,sort_order?:int}>  $rows
     */
    public function saveCatalogs(int $companyId, ?int $leaderUserId, array $rows): void
    {
        if (! Schema::hasTable('sale_optimization_catalogs')) {
            return;
        }

        $keptIds = [];
        foreach (array_values($rows) as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $payload = [
                'company_id' => $companyId,
                'leader_user_id' => $leaderUserId,
                'name' => $name,
                'metrics' => is_array($row['metrics'] ?? null) ? $row['metrics'] : [],
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $model = SaleOptimizationCatalog::query()
                    ->where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();
                if ($model) {
                    $model->fill($payload)->save();
                    $keptIds[] = $model->id;
                    continue;
                }
            }

            $created = SaleOptimizationCatalog::query()->create($payload);
            $keptIds[] = $created->id;
        }

        SaleOptimizationCatalog::query()
            ->where('company_id', $companyId)
            ->when(
                $leaderUserId,
                fn ($q) => $q->where('leader_user_id', $leaderUserId),
                fn ($q) => $q->whereNull('leader_user_id'),
            )
            ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();
    }

    private function thresholds(Request $request): array
    {
        $defaults = ['low' => 80.0, 'high' => 100.0];
        if (! Schema::hasTable('sale_optimization_alert_thresholds')) {
            return $defaults;
        }
        $companyId = $request->user()?->company_id;
        $row = SaleOptimizationAlertThreshold::query()
            ->where('company_id', $companyId)
            ->where('metric_key', 'close_rate')
            ->first();

        return $row
            ? ['low' => (float) $row->low_ratio, 'high' => (float) $row->high_ratio]
            : $defaults;
    }

    private function levels(Request $request): array
    {
        if (! Schema::hasTable('sale_optimization_levels')) {
            return [
                ['label' => 'Chưa tốt', 'tone' => 'bad', 'min_ratio' => 0, 'max_ratio' => 80],
                ['label' => 'Trung bình', 'tone' => 'average', 'min_ratio' => 80, 'max_ratio' => 100],
                ['label' => 'Tốt', 'tone' => 'good', 'min_ratio' => 100, 'max_ratio' => null],
            ];
        }
        $rows = SaleOptimizationLevel::query()
            ->where('company_id', $request->user()?->company_id)
            ->orderBy('sort_order')
            ->get();
        if ($rows->isEmpty()) {
            return [
                ['label' => 'Chưa tốt', 'tone' => 'bad', 'min_ratio' => 0, 'max_ratio' => 80],
                ['label' => 'Trung bình', 'tone' => 'average', 'min_ratio' => 80, 'max_ratio' => 100],
                ['label' => 'Tốt', 'tone' => 'good', 'min_ratio' => 100, 'max_ratio' => null],
            ];
        }

        return $rows->map(fn ($row) => [
            'label' => $row->label,
            'tone' => $row->tone,
            'min_ratio' => (float) $row->min_ratio,
            'max_ratio' => $row->max_ratio !== null ? (float) $row->max_ratio : null,
        ])->all();
    }

    /** @return list<array{id:int,name:string,leader_user_id:?int,metrics:array<string,mixed>,sort_order:int}> */
    private function catalogs(Request $request): array
    {
        if (! Schema::hasTable('sale_optimization_catalogs')) {
            return [];
        }

        $leaderId = (int) ($request->input('sale_leader_id') ?: 0);

        return SaleOptimizationCatalog::query()
            ->where('company_id', $request->user()?->company_id)
            ->when(
                $leaderId > 0,
                fn ($q) => $q->where('leader_user_id', $leaderId),
                fn ($q) => $q->whereNull('leader_user_id'),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SaleOptimizationCatalog $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'leader_user_id' => $row->leader_user_id !== null ? (int) $row->leader_user_id : null,
                'metrics' => is_array($row->metrics) ? $row->metrics : [],
                'sort_order' => (int) $row->sort_order,
            ])
            ->all();
    }

    /** @return array<int, array<string, float>> */
    private function targets(Request $request): array
    {
        if (! Schema::hasTable('sale_optimization_targets')) {
            return [0 => ['close_rate' => 100.0]];
        }
        $map = [0 => ['close_rate' => 100.0]];
        SaleOptimizationTarget::query()
            ->where('company_id', $request->user()?->company_id)
            ->get()
            ->each(function ($row) use (&$map): void {
                $saleId = (int) ($row->sale_user_id ?? 0);
                $map[$saleId][$row->metric_key] = (float) $row->target_value;
            });

        return $map;
    }

    private function tone(?float $ratio, array $thresholds): string
    {
        if ($ratio === null) {
            return 'average';
        }
        if ($ratio < (float) $thresholds['low']) {
            return 'bad';
        }
        if ($ratio < (float) $thresholds['high']) {
            return 'average';
        }

        return 'good';
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function totals(Collection $rows): array
    {
        $keys = [
            'provisional_revenue', 'success_revenue', 'contacts', 'allocated_total', 'allocated_duplicate',
            'allocated_unique', 'closed_contacts', 'untouched', 'cancelled_revenue', 'returned_revenue',
        ];
        $totals = ['sale' => 'Tổng', 'receive_data' => null];
        foreach ($keys as $key) {
            $totals[$key] = 0;
        }
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
        }
        $totals['close_rate'] = round(($totals['closed_contacts'] / max(1, $totals['contacts'])) * 100, 2);
        $totals['avg_order_value'] = (int) round(
            collect($rows)->sum('success_revenue') > 0
                ? collect($rows)->avg('avg_order_value')
                : 0
        );
        $totals['products_per_order'] = round(collect($rows)->avg('products_per_order') ?? 0, 2);
        $totals['revenue_per_contact'] = (int) round($totals['provisional_revenue'] / max(1, $totals['allocated_unique']));
        $totals['tone'] = 'average';

        return $totals;
    }
}
