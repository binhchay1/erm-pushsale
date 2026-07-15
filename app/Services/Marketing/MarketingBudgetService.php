<?php

namespace App\Services\Marketing;

use App\Models\LandingConnection;
use App\Models\MarketingSourceDailyMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Một nguồn ngân sách duy nhất cho toàn hệ thống.
 *
 * - Ngân sách kế hoạch nằm trên landing_connections.
 * - Chi tiêu thực tế theo ngày nằm trên marketing_source_daily_metrics.
 * - Nếu kỳ báo cáo chưa có dòng thực chi, hệ thống dùng ngân sách kế hoạch
 *   được phân bổ đúng theo phần giao nhau của khoảng ngày để không cộng lặp.
 */
final class MarketingBudgetService
{
    public function plannedTotal(LandingConnection $connection): int
    {
        return $connection->plannedBudgetTotal();
    }

    public function plannedForRange(
        LandingConnection $connection,
        CarbonInterface $from,
        CarbonInterface $to,
    ): int {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        if ($to->lt($from) || (int) $connection->budget_amount <= 0) {
            return 0;
        }

        $periodStart = $connection->budget_start_date?->copy()->startOfDay();
        $periodEnd = $connection->budget_end_date?->copy()->endOfDay();

        // Dữ liệu cũ không có kỳ ngân sách: chỉ tính trong kỳ chứa ngày tạo kết nối.
        if (! $periodStart || ! $periodEnd) {
            $created = $connection->created_at?->copy()->startOfDay();

            return $created && $created->betweenIncluded($from, $to)
                ? max(0, (int) $connection->budget_amount)
                : 0;
        }

        $overlapStart = $from->greaterThan($periodStart) ? $from : $periodStart;
        $overlapEnd = $to->lessThan($periodEnd) ? $to : $periodEnd;

        if ($overlapEnd->lt($overlapStart)) {
            return 0;
        }

        $overlapDays = (int) $overlapStart->copy()->startOfDay()->diffInDays($overlapEnd->copy()->startOfDay()) + 1;

        if ($connection->budget_type === 'daily') {
            return max(0, (int) $connection->budget_amount) * $overlapDays;
        }

        $periodStartDay = $periodStart->copy()->startOfDay();
        $periodEndDay = $periodEnd->copy()->startOfDay();
        $periodDays = max(1, (int) $periodStartDay->diffInDays($periodEndDay) + 1);
        $total = max(0, (int) $connection->budget_amount);

        // Phân bổ chính xác đến từng VND. Mỗi ngày nhận phần nguyên; phần dư
        // được dồn vào các ngày cuối kỳ. Tổng các ngày luôn đúng bằng ngân sách.
        $basePerDay = intdiv($total, $periodDays);
        $remainder = $total % $periodDays;
        $extraDays = 0;

        if ($remainder > 0) {
            $overlapStartOffset = (int) $periodStartDay->diffInDays($overlapStart->copy()->startOfDay());
            $overlapEndOffset = (int) $periodStartDay->diffInDays($overlapEnd->copy()->startOfDay());
            $remainderStartOffset = $periodDays - $remainder;
            $intersectionStart = max($overlapStartOffset, $remainderStartOffset);
            $intersectionEnd = min($overlapEndOffset, $periodDays - 1);
            $extraDays = max(0, $intersectionEnd - $intersectionStart + 1);
        }

        return ($basePerDay * $overlapDays) + $extraDays;
    }

    public function actualForSourceIds(Collection|array $sourceIds, CarbonInterface $from, CarbonInterface $to): int
    {
        $ids = collect($sourceIds)->filter()->map('intval')->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) MarketingSourceDailyMetric::query()
            ->whereIn('marketing_source_id', $ids)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->sum('budget');
    }

    public function hasActualForSourceIds(Collection|array $sourceIds, CarbonInterface $from, CarbonInterface $to): bool
    {
        $ids = collect($sourceIds)->filter()->map('intval')->unique()->values();

        return $ids->isNotEmpty() && MarketingSourceDailyMetric::query()
            ->whereIn('marketing_source_id', $ids)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->exists();
    }

    public function plannedForSourceIds(Collection|array $sourceIds, CarbonInterface $from, CarbonInterface $to): int
    {
        $ids = collect($sourceIds)->filter()->map('intval')->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return LandingConnection::query()
            ->whereIn('marketing_source_id', $ids)
            ->get()
            ->sum(fn (LandingConnection $connection): int => $this->plannedForRange($connection, $from, $to));
    }

    /**
     * Chi phí marketing dùng cho báo cáo, ghép theo từng nguồn và từng ngày:
     * có thực chi thì dùng thực chi; chưa có thì dùng kế hoạch phân bổ.
     * Nhờ vậy nhập số liệu dở dang không làm mất ngân sách của nguồn/ngày còn lại.
     *
     * @return array{amount:int, actual:int, planned:int, basis:string}
     */
    public function effectiveForSourceIds(
        Collection|array $sourceIds,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $series = collect($this->dailySeriesForSourceIds($sourceIds, $from, $to));
        $actual = (int) $series->sum('actual');
        $planned = (int) $series->sum('planned');
        $amount = (int) $series->sum('effective');
        $hasActual = $series->contains(fn (array $row): bool => (bool) ($row['has_actual'] ?? false));
        $hasFallback = $series->contains(fn (array $row): bool => (bool) ($row['has_planned_fallback'] ?? false));

        return [
            'amount' => $amount,
            'actual' => $actual,
            'planned' => $planned,
            'basis' => $hasActual ? ($hasFallback ? 'mixed' : 'actual') : ($hasFallback ? 'planned' : 'none'),
        ];
    }

    /** @return list<array{date:string,planned:int,actual:int,effective:int,basis:string,has_actual:bool,has_planned_fallback:bool}> */
    public function dailySeriesForSourceIds(
        Collection|array $sourceIds,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $ids = collect($sourceIds)->filter()->map('intval')->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $actualRows = MarketingSourceDailyMetric::query()
            ->whereIn('marketing_source_id', $ids)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('marketing_source_id, metric_date, SUM(budget) as total_budget')
            ->groupBy('marketing_source_id', 'metric_date')
            ->get();
        $actualMap = $actualRows->keyBy(
            fn (MarketingSourceDailyMetric $row): string => $row->marketing_source_id.'|'.$row->metric_date->toDateString()
        );
        $connectionsBySource = LandingConnection::query()
            ->whereIn('marketing_source_id', $ids)
            ->get()
            ->groupBy('marketing_source_id');

        $rows = [];
        $cursor = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $date = $cursor->toDateString();
            $planned = 0;
            $actual = 0;
            $effective = 0;
            $hasActual = false;
            $hasFallback = false;

            foreach ($ids as $sourceId) {
                $sourcePlanned = (int) collect($connectionsBySource->get($sourceId, collect()))->sum(
                    fn (LandingConnection $connection): int => $this->plannedForRange(
                        $connection,
                        $cursor->copy()->startOfDay(),
                        $cursor->copy()->endOfDay(),
                    )
                );
                $planned += $sourcePlanned;
                $key = $sourceId.'|'.$date;

                if ($actualMap->has($key)) {
                    $sourceActual = (int) ($actualMap->get($key)->total_budget ?? 0);
                    $actual += $sourceActual;
                    $effective += $sourceActual;
                    $hasActual = true;
                } else {
                    $effective += $sourcePlanned;
                    $hasFallback = $hasFallback || $sourcePlanned > 0;
                }
            }

            $rows[] = [
                'date' => $date,
                'planned' => $planned,
                'actual' => $actual,
                'effective' => $effective,
                'basis' => $hasActual ? ($hasFallback ? 'mixed' : 'actual') : ($hasFallback ? 'planned' : 'none'),
                'has_actual' => $hasActual,
                'has_planned_fallback' => $hasFallback,
            ];
            $cursor->addDay();
        }

        return $rows;
    }

}
