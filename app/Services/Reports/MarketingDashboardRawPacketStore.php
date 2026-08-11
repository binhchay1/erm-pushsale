<?php

namespace App\Services\Reports;

use App\Data\MarketingDashboardFilterData;
use App\Enums\InboundEventSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid raw marketing packet reader.
 *
 * Closed historical days are read from report_daily_marketing_packet_facts.
 * Dates that do not have facts yet, and the current day, are read from inbound_events.
 */
class MarketingDashboardRawPacketStore
{
    /** @param Collection<int|string,string> $channels @return Collection<int,object> */
    public function aggregates(MarketingDashboardFilterData $filter, Collection $channels): Collection
    {
        if ($channels->isEmpty()) {
            return collect();
        }

        if (! Schema::hasTable('report_daily_marketing_packet_facts')) {
            return $this->liveAggregates($filter, $channels, $filter->dateFrom, $filter->dateTo);
        }

        $timezone = config('reporting.timezone', config('app.timezone'));
        $from = CarbonImmutable::parse($filter->dateFrom, $timezone)->startOfDay();
        $to = CarbonImmutable::parse($filter->dateTo, $timezone)->endOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $historyTo = $to->lt($today) ? $to : $today->subSecond();
        $rows = collect();

        if ($from->lte($historyTo)) {
            $factDates = DB::table('report_daily_marketing_packet_facts')
                ->whereBetween('metric_date', [$from->toDateString(), $historyTo->toDateString()])
                ->whereIn('channel', $channels->values()->all())
                ->distinct()
                ->pluck('metric_date')
                ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
                ->values();

            if ($factDates->isNotEmpty()) {
                $rows = $rows->merge($this->factAggregates($filter, $channels, $factDates));
            }

            $missingDates = collect();
            for ($day = $from; $day->lte($historyTo); $day = $day->addDay()) {
                if (! $factDates->contains($day->toDateString())) {
                    $missingDates->push($day);
                }
            }

            foreach ($missingDates as $day) {
                $rows = $rows->merge($this->liveAggregates($filter, $channels, $day->startOfDay(), $day->endOfDay()));
            }
        }

        if ($to->gte($today)) {
            $liveFrom = $from->gt($today) ? $from : $today;
            $rows = $rows->merge($this->liveAggregates($filter, $channels, $liveFrom, $to));
        }

        return $this->collapse($rows);
    }

    /** @param Collection<int|string,string> $channels @param Collection<int,string> $dates @return Collection<int,object> */
    private function factAggregates(MarketingDashboardFilterData $filter, Collection $channels, Collection $dates): Collection
    {
        return DB::table('report_daily_marketing_packet_facts')
            ->whereIn('metric_date', $dates->all())
            ->whereIn('channel', $channels->values()->all())
            ->when($filter->marketerId, fn ($q, int $id) => $q->where('marketer_user_id', $id))
            ->when($filter->teamId, fn ($q, int $id) => $q->where('team_id', $id))
            ->when($filter->adChannel, fn ($q, string $value) => $q->where('ad_channel', $value))
            ->when($filter->utmKeyword, function ($q, string $value): void {
                $q->where(function ($utm) use ($value): void {
                    $utm->where('utm_source', 'like', '%'.$value.'%')
                        ->orWhere('utm_campaign', 'like', '%'.$value.'%')
                        ->orWhere('utm_medium', 'like', '%'.$value.'%')
                        ->orWhere('utm_term', 'like', '%'.$value.'%')
                        ->orWhere('utm_content', 'like', '%'.$value.'%');
                });
            })
            ->select([
                'metric_date', 'marketing_source_id', 'parent_marketing_source_id', 'landing_connection_id',
                'landing_connection_source_id', 'marketer_user_id', 'team_id', 'ad_channel', 'source_type',
                'channel', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content', 'status',
            ])
            ->selectRaw('SUM(packet_count) as packet_count')
            ->selectRaw('SUM(primary_packet_count) as primary_packet_count')
            ->selectRaw('SUM(upsale_packet_count) as upsale_packet_count')
            ->selectRaw('SUM(processed_count) as processed_count')
            ->selectRaw('SUM(rejected_count) as rejected_count')
            ->selectRaw('SUM(failed_count) as failed_count')
            ->selectRaw('SUM(no_phone_count) as no_phone_count')
            ->selectRaw('SUM(phone_count) as phone_count')
            ->selectRaw('SUM(unique_phone_count) as unique_phone_count')
            ->selectRaw('SUM(duplicate_packet_count) as duplicate_packet_count')
            ->groupBy([
                'metric_date', 'marketing_source_id', 'parent_marketing_source_id', 'landing_connection_id',
                'landing_connection_source_id', 'marketer_user_id', 'team_id', 'ad_channel', 'source_type',
                'channel', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content', 'status',
            ])
            ->get();
    }

    /** @param Collection<int|string,string> $channels @return Collection<int,object> */
    private function liveAggregates(MarketingDashboardFilterData $filter, Collection $channels, mixed $from, mixed $to): Collection
    {
        $base = DB::table('inbound_events as ie')
            ->join('landing_connection_sources as lcs', function ($join): void {
                $join->on('ie.channel', '=', DB::raw("CONCAT('landing-connection:', lcs.landing_connection_id, ':source:', lcs.id)"));
            })
            ->join('landing_connections as lc', 'lc.id', '=', 'lcs.landing_connection_id')
            ->leftJoin('marketing_sources as ms', 'ms.id', '=', 'lc.marketing_source_id')
            ->leftJoin('marketing_sources as parent_sources', 'parent_sources.id', '=', 'ms.parent_id')
            ->leftJoin('users as marketer_users', function ($join): void {
                $join->on('marketer_users.id', '=', DB::raw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id, lc.marketer_user_id)'));
            })
            ->where('ie.source', InboundEventSource::LandingWebhook->value)
            ->where('ie.created_at', '>=', $from)
            ->where('ie.created_at', '<=', $to)
            ->whereIn('ie.channel', $channels->values()->all())
            ->whereNull('lcs.deleted_at')
            ->whereNull('lc.deleted_at')
            ->selectRaw('DATE(ie.created_at) as metric_date')
            ->selectRaw('COALESCE(lc.marketing_source_id, 0) as marketing_source_id')
            ->selectRaw('COALESCE(ms.parent_id, 0) as parent_marketing_source_id')
            ->selectRaw('lc.id as landing_connection_id')
            ->selectRaw('lcs.id as landing_connection_source_id')
            ->selectRaw('COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id, lc.marketer_user_id, 0) as marketer_user_id')
            ->selectRaw('COALESCE(marketer_users.team_id, 0) as team_id')
            ->selectRaw("LEFT(COALESCE(NULLIF(ms.ad_channel, ''), NULLIF(parent_sources.ad_channel, ''), NULLIF(lc.ad_channel, ''), ''), 120) as ad_channel")
            ->selectRaw("LEFT(COALESCE(NULLIF(lcs.source_type, ''), 'main'), 40) as source_type")
            ->selectRaw('LEFT(ie.channel, 120) as channel')
            ->selectRaw("LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_source')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_source')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_source')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_source')), ''), ''), 180) as utm_source")
            ->selectRaw("LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_campaign')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.campaign')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_campaign')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_campaign')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_campaign')), ''), ''), 220) as utm_campaign")
            ->selectRaw("LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_medium')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_medium')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_medium')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_medium')), ''), ''), 180) as utm_medium")
            ->selectRaw("LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_term')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_term')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_term')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_term')), ''), ''), 220) as utm_term")
            ->selectRaw("LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_content')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_content')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_content')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_content')), ''), ''), 220) as utm_content")
            ->selectRaw("LEFT(COALESCE(NULLIF(ie.status, ''), 'unknown'), 30) as status")
            ->selectRaw("LEFT(REGEXP_REPLACE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.phone')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.customer_phone')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.sdt')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.\"Số điện thoại\"')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.\"số điện thoại\"')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.form.phone')), ''), ''), '[^0-9]', ''), 20) as phone")
            ->selectRaw("CASE WHEN LOWER(COALESCE(lcs.source_type, 'main')) IN ('upsell', 'upsale', 'supplement', 'thank_you') OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.item_type')), '')) IN ('upsell', 'upsale', 'late_upsell', 'late_upsale') OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.landing_source_type')), '')) IN ('upsell', 'upsale', 'late_upsell', 'late_upsale') OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.is_upsell')), '')) IN ('1', 'true', 'yes', 'on') OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.is_upsale')), '')) IN ('1', 'true', 'yes', 'on') THEN 1 ELSE 0 END as is_upsale");

        $query = DB::query()->fromSub($base, 'base')
            ->when($filter->marketerId, fn ($q, int $id) => $q->where('marketer_user_id', $id))
            ->when($filter->teamId, fn ($q, int $id) => $q->where('team_id', $id))
            ->when($filter->adChannel, fn ($q, string $value) => $q->where('ad_channel', $value))
            ->when($filter->utmKeyword, function ($q, string $value): void {
                $needle = '%'.$value.'%';
                $q->where(function ($utm) use ($needle): void {
                    $utm->where('utm_source', 'like', $needle)
                        ->orWhere('utm_campaign', 'like', $needle)
                        ->orWhere('utm_medium', 'like', $needle)
                        ->orWhere('utm_term', 'like', $needle)
                        ->orWhere('utm_content', 'like', $needle);
                });
            })
            ->select([
                'metric_date', 'marketing_source_id', 'parent_marketing_source_id', 'landing_connection_id',
                'landing_connection_source_id', 'marketer_user_id', 'team_id', 'ad_channel', 'source_type',
                'channel', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content', 'status',
            ])
            ->selectRaw('COUNT(*) as packet_count')
            ->selectRaw('SUM(CASE WHEN is_upsale = 0 THEN 1 ELSE 0 END) as primary_packet_count')
            ->selectRaw('SUM(CASE WHEN is_upsale = 1 THEN 1 ELSE 0 END) as upsale_packet_count')
            ->selectRaw("SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_count")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN phone = '' THEN 1 ELSE 0 END) as no_phone_count")
            ->selectRaw("SUM(CASE WHEN phone <> '' THEN 1 ELSE 0 END) as phone_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN phone <> '' THEN phone ELSE NULL END) as unique_phone_count")
            ->selectRaw("GREATEST(0, SUM(CASE WHEN phone <> '' THEN 1 ELSE 0 END) - COUNT(DISTINCT CASE WHEN phone <> '' THEN phone ELSE NULL END)) as duplicate_packet_count")
            ->groupBy([
                'metric_date', 'marketing_source_id', 'parent_marketing_source_id', 'landing_connection_id',
                'landing_connection_source_id', 'marketer_user_id', 'team_id', 'ad_channel', 'source_type',
                'channel', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content', 'status',
            ]);

        return $query->get();
    }

    /** @param Collection<int,object> $rows @return Collection<int,object> */
    private function collapse(Collection $rows): Collection
    {
        return $rows->groupBy(function (object $row): string {
            return implode('|', [
                $row->metric_date ?? '', $row->marketing_source_id ?? 0, $row->parent_marketing_source_id ?? 0,
                $row->landing_connection_id ?? 0, $row->landing_connection_source_id ?? 0,
                $row->marketer_user_id ?? 0, $row->team_id ?? 0, $row->ad_channel ?? '', $row->source_type ?? '',
                $row->channel ?? '', $row->utm_source ?? '', $row->utm_campaign ?? '', $row->utm_medium ?? '',
                $row->utm_term ?? '', $row->utm_content ?? '', $row->status ?? '',
            ]);
        })->map(function (Collection $group): object {
            $base = clone $group->first();
            foreach ($group->slice(1) as $row) {
                foreach (['packet_count', 'primary_packet_count', 'upsale_packet_count', 'processed_count', 'rejected_count', 'failed_count', 'no_phone_count', 'phone_count', 'unique_phone_count', 'duplicate_packet_count'] as $column) {
                    $base->{$column} = (int) ($base->{$column} ?? 0) + (int) ($row->{$column} ?? 0);
                }
            }

            return $base;
        })->values();
    }


}
