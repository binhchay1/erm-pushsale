<?php

namespace App\Services\Reports\SalesLeader;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class OperationConversionReportService
{
    public function __construct(
        private readonly SalesLeaderReportQuery $query,
        private readonly SalesLeaderSaleAggregator $aggregator,
    ) {}

    public function build(Request $request): array
    {
        $stages = $this->query->visibleStages($request);
        $grouped = $this->aggregator->filterGrouped(
            $this->aggregator->groupBySale($this->query->loadOrders($request), $request),
            $request,
        );

        $rows = $grouped->map(function (array $row) use ($stages): array {
            $result = [
                'sale_id' => $row['id'],
                'sale' => $row['name'],
                'sale_account' => $row['account'],
                'total_contacts' => $row['contacts'],
                'total_closed' => $row['closed'],
                'total_rate' => round(($row['closed'] / max(1, $row['contacts'])) * 100, 2),
                'total_revenue' => $row['revenue'],
                'revenue' => $row['revenue'],
            ];
            foreach ($stages as $stage) {
                $metric = $row['stage_metrics'][$stage] ?? ['contacts' => 0, 'closed' => 0, 'revenue' => 0];
                $result[$stage.'_contacts'] = $metric['contacts'];
                $result[$stage.'_closed'] = $metric['closed'];
                $result[$stage.'_rate'] = round(($metric['closed'] / max(1, $metric['contacts'])) * 100, 2);
                $result[$stage.'_revenue'] = $metric['revenue'];
            }

            return $result;
        });

        $sortMetric = trim((string) $request->input('sort_metric', 'total_revenue'));
        $rows = $rows->sortByDesc(match ($sortMetric) {
            'total_contacts' => 'total_contacts',
            'total_closed' => 'total_closed',
            'total_rate' => 'total_rate',
            default => 'total_revenue',
        })->values()->map(function (array $row, int $index): array {
            $row['index'] = $index + 1;

            return $row;
        });

        $totals = $this->totals($rows, $stages);
        $page = $this->query->paginateRows($rows, $request);

        return [
            'data' => $page['data'],
            'meta' => $page['meta'],
            'summary' => [
                'totals' => $totals,
                'stages' => array_map(
                    fn (string $stage) => ['key' => $stage, 'label' => $this->query->stageLabel($stage)],
                    $stages,
                ),
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function totals(Collection $rows, array $stages): array
    {
        $totals = [
            'sale' => 'Tổng',
            'sale_account' => '',
            'total_contacts' => 0,
            'total_closed' => 0,
            'total_revenue' => 0,
            'revenue' => 0,
        ];
        foreach ($stages as $stage) {
            $totals[$stage.'_contacts'] = 0;
            $totals[$stage.'_closed'] = 0;
            $totals[$stage.'_revenue'] = 0;
        }

        foreach ($rows as $row) {
            $totals['total_contacts'] += (int) ($row['total_contacts'] ?? 0);
            $totals['total_closed'] += (int) ($row['total_closed'] ?? 0);
            $totals['total_revenue'] += (int) ($row['total_revenue'] ?? $row['revenue'] ?? 0);
            foreach ($stages as $stage) {
                $totals[$stage.'_contacts'] += (int) ($row[$stage.'_contacts'] ?? 0);
                $totals[$stage.'_closed'] += (int) ($row[$stage.'_closed'] ?? 0);
                $totals[$stage.'_revenue'] += (int) ($row[$stage.'_revenue'] ?? 0);
            }
        }

        $totals['revenue'] = $totals['total_revenue'];
        $totals['total_rate'] = round(($totals['total_closed'] / max(1, $totals['total_contacts'])) * 100, 2);
        foreach ($stages as $stage) {
            $totals[$stage.'_rate'] = round(($totals[$stage.'_closed'] / max(1, $totals[$stage.'_contacts'])) * 100, 2);
        }

        return $totals;
    }
}
