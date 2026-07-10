<?php

namespace App\Support;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Định nghĩa thống nhất "contact" trên mọi dashboard/báo cáo:
 * - 1 lần khách để lại SĐT (form đầu / nguồn ads) = 1 contact.
 * - Chỉ cộng packet nghiệp vụ có counts_as_lead = true.
 * - Không cộng packet upsell/follow-up, duplicate, needs_review hoặc failed.
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
            ->where('counts_as_lead', true)
            ->whereNotIn('status', [
                LeadIngestionStatus::Duplicate,
                LeadIngestionStatus::NeedsReview,
                LeadIngestionStatus::Failed,
            ]);
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

    /**
     * Contact chuẩn theo nguồn, có tương thích dữ liệu legacy.
     *
     * Dữ liệu mới luôn lấy từ lead_ingestions.counts_as_lead. Chỉ những order
     * hoàn toàn chưa từng có bất kỳ LeadIngestion nào mới được cộng như một
     * contact legacy. Nhờ vậy đơn bổ sung tạo từ late-upsell vẫn có doanh thu
     * nhưng tuyệt đối không làm tăng contact/tỷ lệ chốt giả.
     *
     * @param  Collection<int, Order>  $orders  Collection đã áp dụng đúng filter/scope report.
     * @return Collection<int, int> marketing_source_id => contact count
     */
    public static function effectiveCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::addLegacyOrders(
            self::countsBySource($filter),
            $orders,
            'marketing_source_id',
        );
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

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, int> marketer_user_id => contact count
     */
    public static function effectiveCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::addLegacyOrders(
            self::countsByMarketer($filter),
            $orders,
            'marketer_user_id',
        );
    }

    /**
     * Danh sách order thực sự đại diện cho một contact trong collection.
     *
     * - Order có lead chính counts_as_lead=true: được tính.
     * - Order không có bất kỳ ingestion nào (dữ liệu cũ/import legacy): được tính.
     * - Order chỉ được tạo bởi packet bổ sung counts_as_lead=false: không tính.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, int>
     */
    public static function contactOrderIds(Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id')->filter()->map(static fn ($id): int => (int) $id)->unique()->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $trackedOrderIds = LeadIngestion::query()
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique();

        $primaryOrderIds = self::applyCountableScope(LeadIngestion::query())
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique();

        $legacyOrderIds = $orderIds->diff($trackedOrderIds);

        return $primaryOrderIds
            ->merge($legacyOrderIds)
            ->unique()
            ->values();
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

    /**
     * @param  Collection<int, int|string>  $counts
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, int>
     */
    private static function addLegacyOrders(Collection $counts, Collection $orders, string $groupField): Collection
    {
        $normalized = $counts->mapWithKeys(
            static fn ($count, $key): array => [(int) $key => (int) $count],
        );

        $contactOrderIds = self::contactOrderIds($orders);
        if ($contactOrderIds->isEmpty()) {
            return $normalized;
        }

        /*
         * Chỉ cộng các order legacy. Order đã có lead chính đã nằm trong query
         * countable ở trên; cộng lại sẽ gây double-count. Order supplemental
         * đã có ingestion không-countable nên bị loại bởi contactOrderIds().
         */
        $trackedPrimaryOrderIds = self::applyCountableScope(LeadIngestion::query())
            ->whereIn('order_id', $contactOrderIds)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique();

        $legacyOrderIds = $contactOrderIds->diff($trackedPrimaryOrderIds);

        $orders
            ->whereIn('id', $legacyOrderIds)
            ->filter(static fn (Order $order): bool => filled($order->{$groupField}))
            ->groupBy(static fn (Order $order): int => (int) $order->{$groupField})
            ->each(function (Collection $group, int $groupId) use (&$normalized): void {
                $normalized->put($groupId, (int) $normalized->get($groupId, 0) + $group->count());
            });

        return $normalized;
    }
}
