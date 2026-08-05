<?php

namespace App\Support;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Models\LeadIngestion;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Marketing packet contract.
 *
 * Marketing xem traffic landing theo gói tin: gói chính + từng gói upsale đã
 * gộp/xử lý thành công vào đơn. Gói follow-up, duplicate, failed, packet còn
 * review/orphan chưa gộp và mọi upsale chưa có đơn hiệu lực đều không được tính.
 */
final class MarketingPacketMetrics
{
    /** @param Builder<LeadIngestion>|Relation<LeadIngestion, mixed, mixed> $query */
    public static function applyCountableScope(Builder|Relation $query): Builder|Relation
    {
        return $query
            ->whereNotIn('status', [
                LeadIngestionStatus::Duplicate->value,
                LeadIngestionStatus::Failed->value,
                LeadIngestionStatus::NeedsReview->value,
            ])
            ->where(function (Builder $packet): void {
                $packet->where(function (Builder $primary): void {
                    $primary->where('counts_as_lead', true)
                        ->where(function (Builder $type): void {
                            $type->whereNull('packet_type')
                                ->orWhereIn('packet_type', self::primaryTypes());
                        });
                })->orWhere(function (Builder $upsale): void {
                    self::applyValidUpsaleScope($upsale);
                });
            });
    }

    /**
     * Một gói upsale chỉ được tính vào Marketing khi đã xử lý/gắn với đơn.
     * Packet review, orphan chưa xử lý, duplicate hoặc lỗi chỉ dùng audit, không
     * làm tăng contact trên dashboard/report.
     *
     * @param Builder<LeadIngestion>|Relation<LeadIngestion, mixed, mixed> $query
     */
    public static function applyValidUpsaleScope(Builder|Relation $query): Builder|Relation
    {
        return $query
            ->whereIn('packet_type', self::upsaleTypes())
            ->where('counts_as_lead', false)
            ->where('status', LeadIngestionStatus::Processed->value)
            ->where(function (Builder $review): void {
                $review->where('requires_review', false)->orWhereNull('requires_review');
            })
            ->where(function (Builder $linked): void {
                $linked->whereNotNull('order_id')
                    ->orWhereNotNull('related_order_id')
                    ->orWhereHas('parentIngestion.order')
                    ->orWhereHas('parentIngestion.relatedOrder');
            });
    }


    /** @return list<string> */
    public static function primaryTypes(): array
    {
        return [
            LeadPacketType::Lead->value,
            'base', // legacy/test fixtures before LeadPacketType::Lead was standardized
            'main',
            'primary',
        ];
    }

    /** @return list<string> */
    public static function upsaleTypes(): array
    {
        return [
            LeadPacketType::Upsell->value,
            LeadPacketType::LateUpsell->value,
            LeadPacketType::OrphanUpsell->value,
            // Legacy UI/business wording used before the enum standardized on "upsell".
            'upsale',
            'late_upsale',
            'orphan_upsale',
        ];
    }

    public static function packetTypeValue(LeadIngestion $packet): string
    {
        $raw = $packet->getRawOriginal('packet_type');
        if (is_scalar($raw) && trim((string) $raw) !== '') {
            return trim((string) $raw);
        }

        return $packet->packet_type?->value ?? LeadPacketType::Lead->value;
    }

    public static function isUpsale(LeadIngestion $packet): bool
    {
        return in_array(self::packetTypeValue($packet), self::upsaleTypes(), true);
    }

    public static function typeKey(LeadIngestion $packet): string
    {
        return match (self::packetTypeValue($packet)) {
            LeadPacketType::Upsell->value, 'upsale' => 'upsale',
            LeadPacketType::LateUpsell->value, 'late_upsale' => 'late_upsale',
            LeadPacketType::OrphanUpsell->value, 'orphan_upsale' => 'orphan_upsale',
            default => 'primary',
        };
    }

    public static function typeLabel(LeadIngestion $packet): string
    {
        return match (self::typeKey($packet)) {
            'upsale' => __('enums.lead_packet_type.upsell'),
            'late_upsale' => __('enums.lead_packet_type.late_upsell'),
            'orphan_upsale' => __('enums.lead_packet_type.orphan_upsell'),
            default => __('enums.lead_packet_type.lead'),
        };
    }

    public static function effectiveOrder(LeadIngestion $packet): ?Order
    {
        return $packet->order
            ?? $packet->relatedOrder
            ?? $packet->parentIngestion?->order
            ?? $packet->parentIngestion?->relatedOrder;
    }

    /** @return Builder<LeadIngestion> */
    public static function countableQuery(?ReportFilterData $filter = null): Builder
    {
        $query = self::applyCountableScope(LeadIngestion::query());

        if ($filter?->dateFrom && $filter->dateTo) {
            self::applyDateFilter($query, $filter);
        }

        self::applyCustomerTypeScope($query, $filter?->customerType);

        if ($filter?->marketingSourceId) {
            $query->where('marketing_source_id', $filter->marketingSourceId);
        }

        if ($filter?->marketerId) {
            $query->whereHas('marketingSource', function (Builder $source) use ($filter): void {
                $source->where('marketer_user_id', $filter->marketerId)
                    ->orWhereHas('parent', fn (Builder $parent) => $parent->where('marketer_user_id', $filter->marketerId));
            });
        }

        return $query;
    }

    public static function applyDateFilter(Builder $query, ReportFilterData $filter): void
    {
        if (! $filter->dateFrom || ! $filter->dateTo) {
            return;
        }

        $column = match ($filter->dateType) {
            DateType::Closing => 'closed_at',
            DateType::SaleReceived => 'assigned_at',
            DateType::CareUpdate => 'updated_at',
            DateType::Posting => 'created_at',
            default => null,
        };

        if ($column === null) {
            $query->whereBetween('lead_ingestions.created_at', [$filter->dateFrom, $filter->dateTo]);

            return;
        }

        self::constrainByEffectiveOrder(
            $query,
            fn (Builder $order) => $order->whereBetween($column, [$filter->dateFrom, $filter->dateTo]),
        );
    }

    /**
     * Dùng chung cho Marketing dashboard và các báo cáo marketing để đảm bảo:
     * Tất cả = Khách mới + Khách cũ. Packet chưa có đơn được xếp vào Khách mới;
     * packet đã có đơn lấy cờ is_returning_customer của đơn hiệu lực.
     *
     * @param Builder<LeadIngestion>|Relation<LeadIngestion, mixed, mixed> $query
     */
    public static function applyCustomerTypeScope(Builder|Relation $query, ?string $customerType): void
    {
        if (in_array($customerType, ['old', 'returning'], true)) {
            self::constrainByEffectiveOrder($query, fn (Builder $order) => $order->where('is_returning_customer', true));

            return;
        }

        if ($customerType === 'new') {
            $query->where(function (Builder $packet): void {
                $packet->where(function (Builder $withoutOrder): void {
                    $withoutOrder->whereDoesntHave('order')
                        ->whereDoesntHave('relatedOrder')
                        ->whereDoesntHave('parentIngestion.order')
                        ->whereDoesntHave('parentIngestion.relatedOrder');
                })->orWhere(function (Builder $withOrder): void {
                    self::constrainByEffectiveOrder($withOrder, fn (Builder $order) => $order->where(function (Builder $type): void {
                        $type->where('is_returning_customer', false)->orWhereNull('is_returning_customer');
                    }));
                });
            });
        }
    }

    /** @return Collection<int, int> */
    public static function countsBySource(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->whereNotNull('marketing_source_id')
            ->selectRaw('marketing_source_id, COUNT(*) as aggregate')
            ->groupBy('marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function primaryCountsBySource(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->where(function (Builder $packet): void {
                $packet->whereNull('packet_type')
                    ->orWhereIn('packet_type', self::primaryTypes());
            })
            ->whereNotNull('marketing_source_id')
            ->selectRaw('marketing_source_id, COUNT(*) as aggregate')
            ->groupBy('marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function upsaleCountsBySource(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->whereIn('packet_type', self::upsaleTypes())
            ->whereNotNull('marketing_source_id')
            ->selectRaw('marketing_source_id, COUNT(*) as aggregate')
            ->groupBy('marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function countsByMarketer(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->join('marketing_sources', 'lead_ingestions.marketing_source_id', '=', 'marketing_sources.id')
            ->leftJoin('marketing_sources as parent_sources', 'marketing_sources.parent_id', '=', 'parent_sources.id')
            ->selectRaw('COALESCE(marketing_sources.marketer_user_id, parent_sources.marketer_user_id) as marketer_id, COUNT(*) as aggregate')
            ->whereRaw('COALESCE(marketing_sources.marketer_user_id, parent_sources.marketer_user_id) IS NOT NULL')
            ->groupBy('marketer_id')
            ->pluck('aggregate', 'marketer_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function primaryCountsByMarketer(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->where(function (Builder $packet): void {
                $packet->whereNull('lead_ingestions.packet_type')
                    ->orWhereIn('lead_ingestions.packet_type', self::primaryTypes());
            })
            ->join('marketing_sources', 'lead_ingestions.marketing_source_id', '=', 'marketing_sources.id')
            ->leftJoin('marketing_sources as parent_sources', 'marketing_sources.parent_id', '=', 'parent_sources.id')
            ->selectRaw('COALESCE(marketing_sources.marketer_user_id, parent_sources.marketer_user_id) as marketer_id, COUNT(*) as aggregate')
            ->whereRaw('COALESCE(marketing_sources.marketer_user_id, parent_sources.marketer_user_id) IS NOT NULL')
            ->groupBy('marketer_id')
            ->pluck('aggregate', 'marketer_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function upsaleCountsByMarketer(ReportFilterData $filter): Collection
    {
        return self::countableQuery($filter)
            ->whereIn('lead_ingestions.packet_type', self::upsaleTypes())
            ->join('marketing_sources', 'lead_ingestions.marketing_source_id', '=', 'marketing_sources.id')
            ->leftJoin('marketing_sources as parent_sources', 'marketing_sources.parent_id', '=', 'parent_sources.id')
            ->selectRaw('COALESCE(marketing_sources.marketer_user_id, parent_sources.marketer_user_id) as marketer_id, COUNT(*) as aggregate')
            ->whereRaw('COALESCE(marketing_sources.marketer_user_id, parent_sources.marketer_user_id) IS NOT NULL')
            ->groupBy('marketer_id')
            ->pluck('aggregate', 'marketer_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /**
     * Preserve legacy orders that have no ingestion packets at all. Modern
     * orders are already represented by the packet query and are not re-added.
     *
     * @param Collection<int, Order> $orders
     * @return Collection<int, int>
     */
    public static function effectiveCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::addLegacyOrders(self::countsBySource($filter), $orders, 'marketing_source_id');
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectivePrimaryCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::addLegacyOrders(self::primaryCountsBySource($filter), $orders, 'marketing_source_id');
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveUpsaleCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::upsaleCountsBySource($filter)->mapWithKeys(static fn ($count, $key): array => [(int) $key => (int) $count]);
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::addLegacyOrders(self::countsByMarketer($filter), $orders, 'marketer_user_id');
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectivePrimaryCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::addLegacyOrders(self::primaryCountsByMarketer($filter), $orders, 'marketer_user_id');
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveUpsaleCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::upsaleCountsByMarketer($filter)->mapWithKeys(static fn ($count, $key): array => [(int) $key => (int) $count]);
    }


    /**
     * Order legacy không có bất kỳ packet nào. Các order này vẫn được cộng như
     * một gói tin chính để báo cáo cũ không mất số liệu sau khi chuyển contract.
     *
     * @param Collection<int, Order> $orders
     * @return Collection<int, int>
     */
    public static function legacyOrderIds(Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id')->filter()->map(static fn ($id): int => (int) $id)->unique()->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $tracked = LeadIngestion::query()
            ->where(function (Builder $packet) use ($orderIds): void {
                $packet->whereIn('order_id', $orderIds)->orWhereIn('related_order_id', $orderIds);
            })
            ->get(['order_id', 'related_order_id'])
            ->flatMap(static fn (LeadIngestion $packet): array => array_filter([
                $packet->order_id ? (int) $packet->order_id : null,
                $packet->related_order_id ? (int) $packet->related_order_id : null,
            ]))
            ->unique();

        return $orderIds->diff($tracked)->values();
    }

    /** @param callable(Builder<Order>): void $constraint */
    public static function constrainByEffectiveOrder(Builder|Relation $query, callable $constraint): void
    {
        $query->where(function (Builder $packet) use ($constraint): void {
            $packet->whereHas('order', $constraint)
                ->orWhereHas('relatedOrder', $constraint)
                ->orWhereHas('parentIngestion.order', $constraint)
                ->orWhereHas('parentIngestion.relatedOrder', $constraint);
        });
    }


    /** @param Collection<int, int|string> $counts @param Collection<int, Order> $orders @return Collection<int, int> */
    private static function addLegacyOrders(Collection $counts, Collection $orders, string $groupField): Collection
    {
        $normalized = $counts->mapWithKeys(static fn ($count, $key): array => [(int) $key => (int) $count]);
        $legacyOrderIds = self::legacyOrderIds($orders);

        if ($legacyOrderIds->isEmpty()) {
            return $normalized;
        }

        $orders->whereIn('id', $legacyOrderIds)
            ->filter(static fn (Order $order): bool => filled($order->{$groupField}))
            ->groupBy(static fn (Order $order): int => (int) $order->{$groupField})
            ->each(function (Collection $group, int $groupId) use (&$normalized): void {
                $normalized->put($groupId, (int) $normalized->get($groupId, 0) + $group->count());
            });

        return $normalized;
    }
}
