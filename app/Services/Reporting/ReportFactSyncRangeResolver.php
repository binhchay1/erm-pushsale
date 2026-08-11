<?php

namespace App\Services\Reporting;

use App\Enums\DateType;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportFactSyncRangeResolver
{
    /**
     * Source columns that can affect analytical report facts. The resolver uses these
     * to discover the true historical date range in production DB instead of forcing
     * operators to guess --from/--to values.
     *
     * @return array<string,list<string>>
     */
    public function sourceColumns(): array
    {
        $orders = array_values(array_unique(array_filter(array_map(
            static fn (DateType $dateType) => $dateType->orderColumn(),
            DateType::cases(),
        ))));

        return [
            'lead_ingestions' => ['created_at'],
            'inbound_events' => ['created_at'],
            'orders' => array_values(array_unique(array_merge(['created_at', 'updated_at', 'closed_at', 'data_arrived_at'], $orders))),
            'order_items' => ['created_at', 'updated_at'],
            'shipments' => ['created_at', 'updated_at', 'delivered_at', 'returned_at', 'cod_remitted_at'],
            'shipping_webhook_events' => ['created_at', 'received_at', 'occurred_at'],
            'shipping_status_events' => ['created_at', 'occurred_at'],
            'inventory_movements' => ['created_at', 'movement_at'],
            'stock_movements' => ['created_at', 'movement_at'],
            'warehouse_inventory_movements' => ['created_at', 'movement_at'],
        ];
    }

    /** @return Collection<int,array{company_id:int,from:CarbonImmutable,to:CarbonImmutable,source_count:int}> */
    public function ranges(?int $companyId = null, ?string $from = null, ?string $to = null, bool $includeToday = true): Collection
    {
        $timezone = config('reporting.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $maxTo = $includeToday ? $today : $today->subDay();
        $manualFrom = $from ? CarbonImmutable::parse($from, $timezone)->startOfDay() : null;
        $manualTo = $to ? CarbonImmutable::parse($to, $timezone)->startOfDay() : null;

        return Company::query()
            ->when($companyId, fn ($q) => $q->whereKey($companyId))
            ->pluck('id')
            ->map(function ($id) use ($manualFrom, $manualTo, $maxTo, $includeToday): ?array {
                $detected = $this->detectedRangeForCompany((int) $id);

                $from = $manualFrom ?? $detected['from'];
                $to = $manualTo ?? $detected['to'];

                if (! $from || ! $to) {
                    return null;
                }

                if (! $includeToday && $to->greaterThan($maxTo)) {
                    $to = $maxTo;
                }

                if ($to->greaterThan($maxTo)) {
                    $to = $maxTo;
                }

                if ($from->greaterThan($to)) {
                    return null;
                }

                return [
                    'company_id' => (int) $id,
                    'from' => $from,
                    'to' => $to,
                    'source_count' => $detected['source_count'],
                ];
            })
            ->filter()
            ->values();
    }

    /** @return array{from:?CarbonImmutable,to:?CarbonImmutable,source_count:int} */
    public function detectedRangeForCompany(int $companyId): array
    {
        $timezone = config('reporting.timezone');
        $min = null;
        $max = null;
        $sourceCount = 0;

        foreach ($this->sourceColumns() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $query = DB::table($table)->whereNotNull($column);
                if (Schema::hasColumn($table, 'company_id')) {
                    $query->where('company_id', $companyId);
                }

                $bounds = $query->selectRaw("MIN({$column}) as min_at, MAX({$column}) as max_at, COUNT(*) as row_count")->first();
                if (! $bounds || ! $bounds->min_at || ! $bounds->max_at) {
                    continue;
                }

                $sourceCount += (int) ($bounds->row_count ?? 0);
                $candidateMin = CarbonImmutable::parse($bounds->min_at, $timezone)->startOfDay();
                $candidateMax = CarbonImmutable::parse($bounds->max_at, $timezone)->startOfDay();

                $min = $min ? $min->min($candidateMin) : $candidateMin;
                $max = $max ? $max->max($candidateMax) : $candidateMax;
            }
        }

        return ['from' => $min, 'to' => $max, 'source_count' => $sourceCount];
    }
}
