<?php

namespace App\Support;

use App\Data\MarketingDashboardFilterData;
use App\Data\ReportFilterData;
use App\Enums\InboundEventSource;
use App\Models\InboundEvent;
use App\Models\LandingConnectionSource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raw marketing packet contract.
 *
 * Marketing phải đối soát theo đúng gói tin landing server đã nhận trong
 * inbound_events. Các bước xử lý sau đó như chống trùng SĐT, review, tạo đơn
 * hoặc chia data cho Sale chỉ là số liệu phụ, không làm giảm số raw marketing.
 */
final class MarketingRawPacketMetrics
{
    /** @return Builder<InboundEvent> */
    public static function query(ReportFilterData|MarketingDashboardFilterData $filter, ?Collection $sourceIds = null): Builder
    {
        $query = InboundEvent::query()
            ->from('inbound_events')
            ->join('landing_connection_sources as lcs', function ($join): void {
                $join->on('inbound_events.channel', '=', DB::raw("CONCAT('landing-connection:', lcs.landing_connection_id, ':source:', lcs.id)"));
            })
            ->join('landing_connections as lc', 'lc.id', '=', 'lcs.landing_connection_id')
            ->join('marketing_sources as ms', 'ms.id', '=', 'lc.marketing_source_id')
            ->leftJoin('marketing_sources as parent_sources', 'parent_sources.id', '=', 'ms.parent_id')
            ->leftJoin('users as marketer_users', function ($join): void {
                $join->on('marketer_users.id', '=', DB::raw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id)'));
            })
            ->leftJoin('teams as marketer_teams', 'marketer_teams.id', '=', 'marketer_users.team_id')
            ->where('inbound_events.source', InboundEventSource::LandingWebhook->value)
            ->whereBetween('inbound_events.created_at', [$filter->dateFrom, $filter->dateTo])
            ->whereNull('lcs.deleted_at')
            ->whereNull('lc.deleted_at');

        if ($sourceIds !== null) {
            $ids = $sourceIds->filter()->map(static fn ($id): int => (int) $id)->unique()->values();
            if ($ids->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('lc.marketing_source_id', $ids->all());
            }
        }

        self::applyCommonScopes($query, $filter);

        return $query;
    }

    /** @return Collection<int, int> */
    public static function countsBySource(ReportFilterData $filter): Collection
    {
        return self::query($filter)
            ->selectRaw('lc.marketing_source_id as marketing_source_id, COUNT(inbound_events.id) as aggregate')
            ->groupBy('lc.marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function primaryCountsBySource(ReportFilterData $filter): Collection
    {
        return self::query($filter)
            ->whereNotIn('lcs.source_type', self::upsaleSourceTypes())
            ->selectRaw('lc.marketing_source_id as marketing_source_id, COUNT(inbound_events.id) as aggregate')
            ->groupBy('lc.marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function upsaleCountsBySource(ReportFilterData $filter): Collection
    {
        return self::query($filter)
            ->whereIn('lcs.source_type', self::upsaleSourceTypes())
            ->selectRaw('lc.marketing_source_id as marketing_source_id, COUNT(inbound_events.id) as aggregate')
            ->groupBy('lc.marketing_source_id')
            ->pluck('aggregate', 'marketing_source_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function countsByMarketer(ReportFilterData $filter): Collection
    {
        return self::query($filter)
            ->selectRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) as marketer_id, COUNT(inbound_events.id) as aggregate')
            ->whereRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) IS NOT NULL')
            ->groupBy('marketer_id')
            ->pluck('aggregate', 'marketer_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function primaryCountsByMarketer(ReportFilterData $filter): Collection
    {
        return self::query($filter)
            ->whereNotIn('lcs.source_type', self::upsaleSourceTypes())
            ->selectRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) as marketer_id, COUNT(inbound_events.id) as aggregate')
            ->whereRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) IS NOT NULL')
            ->groupBy('marketer_id')
            ->pluck('aggregate', 'marketer_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @return Collection<int, int> */
    public static function upsaleCountsByMarketer(ReportFilterData $filter): Collection
    {
        return self::query($filter)
            ->whereIn('lcs.source_type', self::upsaleSourceTypes())
            ->selectRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) as marketer_id, COUNT(inbound_events.id) as aggregate')
            ->whereRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) IS NOT NULL')
            ->groupBy('marketer_id')
            ->pluck('aggregate', 'marketer_id')
            ->map(static fn ($value): int => (int) $value);
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::rawFirst(self::countsBySource($filter), MarketingPacketMetrics::effectiveCountsBySource($filter, $orders));
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectivePrimaryCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::rawFirst(self::primaryCountsBySource($filter), MarketingPacketMetrics::effectivePrimaryCountsBySource($filter, $orders));
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveUpsaleCountsBySource(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::rawFirst(self::upsaleCountsBySource($filter), MarketingPacketMetrics::effectiveUpsaleCountsBySource($filter, $orders));
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::rawFirst(self::countsByMarketer($filter), MarketingPacketMetrics::effectiveCountsByMarketer($filter, $orders));
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectivePrimaryCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::rawFirst(self::primaryCountsByMarketer($filter), MarketingPacketMetrics::effectivePrimaryCountsByMarketer($filter, $orders));
    }

    /** @param Collection<int, Order> $orders @return Collection<int, int> */
    public static function effectiveUpsaleCountsByMarketer(ReportFilterData $filter, Collection $orders): Collection
    {
        return self::rawFirst(self::upsaleCountsByMarketer($filter), MarketingPacketMetrics::effectiveUpsaleCountsByMarketer($filter, $orders));
    }

    /** @return list<string> */
    public static function upsaleSourceTypes(): array
    {
        return [
            LandingConnectionSource::TYPE_UPSELL,
            'upsale',
            'supplement',
            LandingConnectionSource::TYPE_THANK_YOU,
        ];
    }

    private static function applyCommonScopes(Builder $query, ReportFilterData|MarketingDashboardFilterData $filter): void
    {
        if ($filter instanceof ReportFilterData && $filter->marketingSourceId) {
            $query->where(function (Builder $source) use ($filter): void {
                $source->where('ms.id', $filter->marketingSourceId)
                    ->orWhere('ms.parent_id', $filter->marketingSourceId)
                    ->orWhere('parent_sources.id', $filter->marketingSourceId);
            });
        }

        if ($filter->sourceType) {
            match ($filter->sourceType) {
                'facebook' => $query->where(function (Builder $source): void {
                    $source->where('ms.ad_channel', 'like', '%Facebook%')
                        ->orWhere('parent_sources.ad_channel', 'like', '%Facebook%')
                        ->orWhere('lc.ad_channel', 'like', '%Facebook%');
                }),
                'website' => $query->where(function (Builder $source): void {
                    $source->where('ms.ad_channel', 'like', '%Website%')
                        ->orWhere('parent_sources.ad_channel', 'like', '%Website%')
                        ->orWhere('lc.ad_channel', 'like', '%Website%');
                }),
                'landing' => $query->where(function (Builder $source): void {
                    $source->whereNull('ms.ad_channel')
                        ->orWhere('ms.ad_channel', 'not like', '%Website%')
                        ->orWhereNull('lc.ad_channel')
                        ->orWhere('lc.ad_channel', 'not like', '%Website%');
                }),
                default => null,
            };
        }

        if ($filter->marketerId) {
            $query->whereRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id) = ?', [$filter->marketerId]);
        }

        $teamId = $filter instanceof ReportFilterData ? ($filter->marketingTeamId ?? $filter->teamId) : $filter->teamId;
        if ($teamId) {
            $query->where('marketer_users.team_id', $teamId);
        }

        $leaderId = $filter instanceof ReportFilterData ? ($filter->marketingTeamLeaderId ?? $filter->teamLeaderId) : $filter->teamLeaderId;
        if ($leaderId) {
            $query->where(function (Builder $leader) use ($leaderId): void {
                $leader->where('marketer_users.manager_user_id', $leaderId)
                    ->orWhere('marketer_users.id', $leaderId)
                    ->orWhere('marketer_teams.leader_user_id', $leaderId);
            });
        }

        if ($filter->productId) {
            $query->where(function (Builder $product) use ($filter): void {
                $product->where('ms.product_id', $filter->productId)
                    ->orWhere('parent_sources.product_id', $filter->productId)
                    ->orWhereExists(function ($exists) use ($filter): void {
                        $exists->selectRaw('1')
                            ->from('landing_connection_products as lcp')
                            ->whereColumn('lcp.landing_connection_id', 'lc.id')
                            ->where('lcp.product_id', $filter->productId);
                    });
            });
        }

        if ($filter->parentProductId) {
            $query->where(function (Builder $product) use ($filter): void {
                $product->whereExists(function ($exists) use ($filter): void {
                    $exists->selectRaw('1')
                        ->from('products as p')
                        ->whereColumn('p.id', 'ms.product_id')
                        ->where(function ($p) use ($filter): void {
                            $p->where('p.id', $filter->parentProductId)->orWhere('p.parent_id', $filter->parentProductId);
                        });
                })->orWhereExists(function ($exists) use ($filter): void {
                    $exists->selectRaw('1')
                        ->from('products as pp')
                        ->whereColumn('pp.id', 'parent_sources.product_id')
                        ->where(function ($p) use ($filter): void {
                            $p->where('pp.id', $filter->parentProductId)->orWhere('pp.parent_id', $filter->parentProductId);
                        });
                })->orWhereExists(function ($exists) use ($filter): void {
                    $exists->selectRaw('1')
                        ->from('landing_connection_products as lcp_parent')
                        ->join('products as lp', 'lp.id', '=', 'lcp_parent.product_id')
                        ->whereColumn('lcp_parent.landing_connection_id', 'lc.id')
                        ->where(function ($p) use ($filter): void {
                            $p->where('lp.id', $filter->parentProductId)->orWhere('lp.parent_id', $filter->parentProductId);
                        });
                });
            });
        }

        if ($filter instanceof MarketingDashboardFilterData) {
            if ($filter->adChannel) {
                $query->where(function (Builder $channel) use ($filter): void {
                    $channel->where('ms.ad_channel', $filter->adChannel)
                        ->orWhere('parent_sources.ad_channel', $filter->adChannel)
                        ->orWhere('lc.ad_channel', $filter->adChannel);
                });
            }

            if ($filter->sourceKeyword) {
                $needle = '%'.$filter->sourceKeyword.'%';
                $query->where(function (Builder $keyword) use ($needle): void {
                    $keyword->where('ms.name', 'like', $needle)
                        ->orWhere('parent_sources.name', 'like', $needle)
                        ->orWhere('lc.name', 'like', $needle)
                        ->orWhere('lcs.name', 'like', $needle);
                });
            }

            if ($filter->utmKeyword) {
                $needle = '%'.$filter->utmKeyword.'%';
                $query->where(function (Builder $utm) use ($needle): void {
                    $utm->whereRaw('LOWER(CAST(inbound_events.payload AS CHAR)) LIKE LOWER(?)', [$needle])
                        ->orWhere('ms.utm_source', 'like', $needle)
                        ->orWhere('ms.utm_campaign', 'like', $needle);
                });
            }
        }
    }

    /** @return Collection<int, int> */
    private static function rawFirst(Collection $raw, Collection $fallback): Collection
    {
        $merged = $fallback->mapWithKeys(static fn ($count, $key): array => [(int) $key => (int) $count]);
        $raw->each(static function ($count, $key) use (&$merged): void {
            $merged->put((int) $key, (int) $count);
        });

        return $merged;
    }
}
