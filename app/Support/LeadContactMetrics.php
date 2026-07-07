<?php

namespace App\Support;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Định nghĩa thống nhất "contact" trên mọi dashboard/báo cáo:
 * - 1 lần khách để lại SĐT (form đầu / nguồn ads) = 1 contact.
 * - Không cộng dòng audit upsell (:upsell), duplicate, failed.
 * - Lọc ngày theo date_type:
 *   + Mặc định (ngày data về) = thời điểm lead đổ về = lead_ingestions.created_at.
 *   + Ngày chốt / ngày sale nhận = theo mốc tương ứng trên đơn.
 *   Dùng chung 1 mốc created_at cho toàn bộ báo cáo để mọi con số khớp tuyệt đối.
 */
final class LeadContactMetrics
{
    /** @param  Builder<LeadIngestion>  $query */
    public static function applyCountableScope(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [
                LeadIngestionStatus::Duplicate,
                LeadIngestionStatus::Failed,
            ])
            ->where(function (Builder $q) {
                $q->whereNull('external_id')
                    ->orWhere('external_id', 'not like', '%:upsell');
            });
    }

    /** @return Builder<LeadIngestion> */
    public static function countableQuery(?ReportFilterData $filter = null): Builder
    {
        $query = self::applyCountableScope(LeadIngestion::query());

        if ($filter?->dateFrom && $filter->dateTo) {
            self::applyDateFilter($query, $filter);
        }

        return $query;
    }

    /** @param  Builder<LeadIngestion>  $query */
    public static function applyDateFilter(Builder $query, ReportFilterData $filter): void
    {
        if (! $filter->dateFrom || ! $filter->dateTo) {
            return;
        }

        match ($filter->dateType) {
            DateType::Closing => $query->whereHas(
                'order',
                fn (Builder $q) => $q->whereBetween('closed_at', [$filter->dateFrom, $filter->dateTo]),
            ),
            DateType::SaleReceived => $query->whereHas(
                'order',
                fn (Builder $q) => $q->whereBetween('assigned_at', [$filter->dateFrom, $filter->dateTo]),
            ),
            default => $query->whereBetween('lead_ingestions.created_at', [$filter->dateFrom, $filter->dateTo]),
        };
    }

    /** @return Collection<int, int> marketing_source_id => contact count */
    public static function countsBySource(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->whereNotNull('marketing_source_id')
            ->selectRaw('marketing_source_id, COUNT(*) as aggregate')
            ->groupBy('marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id');
    }

    /** @return Collection<int, int> marketer_user_id => contact count */
    public static function countsByMarketer(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->join('marketing_sources', 'lead_ingestions.marketing_source_id', '=', 'marketing_sources.id')
            ->whereNotNull('marketing_sources.marketer_user_id')
            ->selectRaw('marketing_sources.marketer_user_id as marketer_id, COUNT(*) as aggregate')
            ->groupBy('marketing_sources.marketer_user_id')
            ->pluck('aggregate', 'marketer_id');
    }

    public static function countForMarketer(int $marketerId, ReportFilterData $filter): int
    {
        return (int) self::countableQuery($filter)
            ->whereHas('marketingSource', fn (Builder $q) => $q->where('marketer_user_id', $marketerId))
            ->count();
    }

    public static function countToday(?int $marketerId = null, ?array $sourceIds = null): int
    {
        $today = now();
        $filter = new ReportFilterData(
            dateFrom: $today->copy()->startOfDay(),
            dateTo: $today->copy()->endOfDay(),
        );

        $query = self::countableQuery($filter);

        if ($marketerId !== null) {
            $query->whereHas('marketingSource', fn (Builder $q) => $q->where('marketer_user_id', $marketerId));
        }

        if ($sourceIds !== null && $sourceIds !== []) {
            $query->whereIn('marketing_source_id', $sourceIds);
        }

        return (int) $query->count();
    }

    public static function countOnDay(Carbon $day, ?int $marketerId = null): int
    {
        $filter = new ReportFilterData(
            dateFrom: $day->copy()->startOfDay(),
            dateTo: $day->copy()->endOfDay(),
        );

        return $marketerId !== null
            ? self::countForMarketer($marketerId, $filter)
            : (int) self::countableQuery($filter)->count();
    }
}
