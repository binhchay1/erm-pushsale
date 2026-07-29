<?php

namespace App\Services\Reports\SalesLeader;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class SalesWorkReportService
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

        $rows = $grouped->map(function (array $row, int $index) use ($stages): array {
            $result = [
                'index' => $index + 1,
                'sale_id' => $row['id'],
                'sale' => $row['name'],
                'sale_account' => $row['account'],
                'total_contacts' => $row['contacts'],
                'untouched' => $row['untouched'],
            ];
            foreach ($stages as $stage) {
                $metric = $row['stage_metrics'][$stage] ?? ['contacts' => 0, 'untouched' => 0];
                $result[$stage.'_contacts'] = $metric['contacts'];
                $result[$stage.'_untouched'] = $metric['untouched'];
            }

            return $result;
        })->values();

        $totals = $this->totals($rows, $stages);
        $page = $this->query->paginateRows($rows, $request);

        return [
            'data' => $page['data'],
            'meta' => $page['meta'],
            'summary' => [
                'totals' => $totals,
                'stages' => array_map(
                    fn (string $stage) => ['key' => $stage, 'label' => SalesLeaderReportQuery::STAGE_LABELS[$stage] ?? $stage],
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
            'untouched' => 0,
        ];
        foreach ($stages as $stage) {
            $totals[$stage.'_contacts'] = 0;
            $totals[$stage.'_untouched'] = 0;
        }
        foreach ($rows as $row) {
            $totals['total_contacts'] += (int) ($row['total_contacts'] ?? 0);
            $totals['untouched'] += (int) ($row['untouched'] ?? 0);
            foreach ($stages as $stage) {
                $totals[$stage.'_contacts'] += (int) ($row[$stage.'_contacts'] ?? 0);
                $totals[$stage.'_untouched'] += (int) ($row[$stage.'_untouched'] ?? 0);
            }
        }

        return $totals;
    }
}
