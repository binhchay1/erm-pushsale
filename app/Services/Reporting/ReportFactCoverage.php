<?php

namespace App\Services\Reporting;

use App\Data\MarketingDashboardFilterData;
use App\Data\ReportFilterData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class ReportFactCoverage
{
    /** @return array<string,array<string,mixed>> */
    public function facts(): array
    {
        return (array) config('reporting_dimensions.facts', []);
    }

    /** @return array<string,array{table:string,ready:bool,missing_columns:list<string>}> */
    public function databaseStatus(): array
    {
        $status = [];
        foreach ($this->facts() as $key => $definition) {
            $table = (string) ($definition['table'] ?? '');
            $columns = array_values(array_unique(array_merge(
                Arr::wrap($definition['dimensions'] ?? []),
                Arr::wrap($definition['metrics'] ?? []),
            )));
            $missing = [];
            if ($table === '' || ! Schema::hasTable($table)) {
                $missing = $columns;
            } else {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missing[] = $column;
                    }
                }
            }

            $status[$key] = [
                'table' => $table,
                'ready' => $table !== '' && Schema::hasTable($table) && $missing === [],
                'missing_columns' => $missing,
            ];
        }

        return $status;
    }

    /** @return list<string> */
    public function unsupportedReportFilters(ReportFilterData $filter): array
    {
        $unsupported = [];
        foreach ((array) config('reporting_dimensions.live_only_filters', []) as $field) {
            $property = str($field)->camel()->toString();
            $value = $filter->{$property} ?? null;
            if (is_bool($value) ? $value : ($value !== null && $value !== '')) {
                $unsupported[] = $field;
            }
        }

        if ($filter->productId || $filter->parentProductId) {
            $unsupported[] = 'order_level_product_filter';
        }

        return array_values(array_unique($unsupported));
    }

    /** @return list<string> */
    public function unsupportedMarketingDashboardFilters(MarketingDashboardFilterData $filter): array
    {
        $unsupported = [];
        foreach (['source_keyword', 'product_id', 'parent_product_id', 'customer_type', 'contact_mode', 'operation_scope'] as $field) {
            $property = str($field)->camel()->toString();
            $value = $filter->{$property} ?? null;
            if ($value !== null && $value !== '') {
                $unsupported[] = $field;
            }
        }

        return $unsupported;
    }

    /** @return array<string,mixed> */
    public function contract(): array
    {
        return [
            'facts' => $this->facts(),
            'database' => $this->databaseStatus(),
            'live_only_filters' => (array) config('reporting_dimensions.live_only_filters', []),
            'hybrid_policy' => (array) config('reporting_dimensions.hybrid_policy', []),
        ];
    }
}
