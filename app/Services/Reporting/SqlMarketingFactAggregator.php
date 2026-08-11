<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SqlMarketingFactAggregator
{
    public function aggregateDate(CarbonInterface|string $date, ?int $companyId = null): array
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->timezone(config('reporting.timezone'))->startOfDay()
            : CarbonImmutable::parse($date, config('reporting.timezone'))->startOfDay();

        $lockKey = 'reporting:sql-marketing-facts:'.($companyId ?: 'all').':'.$day->toDateString();
        $lock = Cache::lock($lockKey, 900);
        if (! $lock->get()) {
            return [
                'date' => $day->toDateString(),
                'company_id' => $companyId,
                'locked' => true,
                'marketing_fact_rows' => 0,
                'marketing_packet_fact_rows' => 0,
            ];
        }

        try {
            return DB::transaction(function () use ($day, $companyId): array {
                $marketingFactRows = $this->aggregateMarketingFactsDate($day, $companyId);
                $packetFactRows = $this->aggregateMarketingPacketFactsDate($day, $companyId);

                return [
                    'date' => $day->toDateString(),
                    'company_id' => $companyId,
                    'locked' => false,
                    'marketing_fact_rows' => $marketingFactRows,
                    'marketing_packet_fact_rows' => $packetFactRows,
                ];
            }, 3);
        } finally {
            $lock->release();
        }
    }

    public function aggregateMarketingFactsDate(CarbonInterface|string $date, ?int $companyId = null): int
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->timezone(config('reporting.timezone'))->startOfDay()
            : CarbonImmutable::parse($date, config('reporting.timezone'))->startOfDay();
        $from = $day->toDateTimeString();
        $to = $day->addDay()->toDateTimeString();
        $factDate = $day->toDateString();

        DB::statement(
            $companyId
                ? 'DELETE FROM report_daily_marketing_facts WHERE fact_date = ? AND company_id = ?'
                : 'DELETE FROM report_daily_marketing_facts WHERE fact_date = ?',
            $companyId ? [$factDate, $companyId] : [$factDate]
        );

        DB::statement($this->marketingFactsInsertSql($companyId !== null), $this->marketingFactsBindings($factDate, $from, $to, $companyId));

        return (int) DB::table('report_daily_marketing_facts')
            ->whereDate('fact_date', $factDate)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();
    }

    public function aggregateMarketingPacketFactsDate(CarbonInterface|string $date, ?int $companyId = null): int
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->timezone(config('reporting.timezone'))->startOfDay()
            : CarbonImmutable::parse($date, config('reporting.timezone'))->startOfDay();
        $from = $day->toDateTimeString();
        $to = $day->addDay()->toDateTimeString();
        $factDate = $day->toDateString();

        DB::statement(
            $companyId
                ? 'DELETE FROM report_daily_marketing_packet_facts WHERE metric_date = ? AND company_id = ?'
                : 'DELETE FROM report_daily_marketing_packet_facts WHERE metric_date = ?',
            $companyId ? [$factDate, $companyId] : [$factDate]
        );

        DB::statement($this->marketingPacketFactsInsertSql($companyId !== null), $this->marketingPacketBindings($factDate, $from, $to, $companyId));

        return (int) DB::table('report_daily_marketing_packet_facts')
            ->whereDate('metric_date', $factDate)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();
    }

    /** @return array<int, mixed> */
    private function marketingFactsBindings(string $factDate, string $from, string $to, ?int $companyId): array
    {
        $bindings = [$factDate, $from, $to];
        if ($companyId !== null) {
            $bindings[] = $companyId;
        }
        $bindings[] = $from;
        $bindings[] = $to;
        if ($companyId !== null) {
            $bindings[] = $companyId;
        }

        return $bindings;
    }

    /** @return array<int, mixed> */
    private function marketingPacketBindings(string $factDate, string $from, string $to, ?int $companyId): array
    {
        $bindings = [$factDate, $from, $to];
        if ($companyId !== null) {
            $bindings[] = $companyId;
        }

        return $bindings;
    }

    private function marketingFactsInsertSql(bool $hasCompanyFilter): string
    {
        $companyWhere = $hasCompanyFilter ? ' AND li.company_id = ? ' : '';
        $companyWhereRevenue = $hasCompanyFilter ? ' AND li.company_id = ? ' : '';

        return <<<SQL
INSERT INTO report_daily_marketing_facts (
    fact_date,
    company_id,
    marketing_source_id,
    utm_campaign,
    status,
    total_leads,
    total_valid_leads,
    total_revenue,
    last_aggregated_at,
    created_at,
    updated_at
)
SELECT
    lead_agg.fact_date,
    lead_agg.company_id,
    lead_agg.marketing_source_id,
    lead_agg.utm_campaign,
    lead_agg.status,
    lead_agg.total_leads,
    lead_agg.total_valid_leads,
    COALESCE(revenue_agg.total_revenue, 0) AS total_revenue,
    NOW() AS last_aggregated_at,
    NOW() AS created_at,
    NOW() AS updated_at
FROM (
    SELECT
        ? AS fact_date,
        li.company_id,
        COALESCE(li.marketing_source_id, 0) AS marketing_source_id,
        COALESCE(NULLIF(li.utm_campaign, ''), '') AS utm_campaign,
        COALESCE(NULLIF(li.status, ''), 'unknown') AS status,
        COUNT(*) AS total_leads,
        SUM(CASE
            WHEN li.status = 'processed'
             AND COALESCE(li.requires_review, 0) = 0
             AND COALESCE(li.counts_as_lead, 0) = 1
            THEN 1 ELSE 0
        END) AS total_valid_leads
    FROM lead_ingestions li
    WHERE li.created_at >= ?
      AND li.created_at < ?
      {$companyWhere}
    GROUP BY
        li.company_id,
        COALESCE(li.marketing_source_id, 0),
        COALESCE(NULLIF(li.utm_campaign, ''), ''),
        COALESCE(NULLIF(li.status, ''), 'unknown')
) lead_agg
LEFT JOIN (
    SELECT
        revenue_rows.fact_date,
        revenue_rows.company_id,
        revenue_rows.marketing_source_id,
        revenue_rows.utm_campaign,
        revenue_rows.status,
        SUM(revenue_rows.order_total) AS total_revenue
    FROM (
        SELECT DISTINCT
            DATE(li.created_at) AS fact_date,
            li.company_id,
            COALESCE(li.marketing_source_id, 0) AS marketing_source_id,
            COALESCE(NULLIF(li.utm_campaign, ''), '') AS utm_campaign,
            COALESCE(NULLIF(li.status, ''), 'unknown') AS status,
            o.id AS order_id,
            COALESCE(o.total, 0) AS order_total
        FROM lead_ingestions li
        INNER JOIN orders o ON o.id = COALESCE(li.order_id, li.related_order_id)
        WHERE li.created_at >= ?
          AND li.created_at < ?
          {$companyWhereRevenue}
          AND li.status = 'processed'
          AND COALESCE(li.requires_review, 0) = 0
    ) revenue_rows
    GROUP BY
        revenue_rows.fact_date,
        revenue_rows.company_id,
        revenue_rows.marketing_source_id,
        revenue_rows.utm_campaign,
        revenue_rows.status
) revenue_agg
    ON revenue_agg.fact_date = lead_agg.fact_date
   AND revenue_agg.company_id = lead_agg.company_id
   AND revenue_agg.marketing_source_id = lead_agg.marketing_source_id
   AND revenue_agg.utm_campaign = lead_agg.utm_campaign
   AND revenue_agg.status = lead_agg.status
ON DUPLICATE KEY UPDATE
    total_leads = VALUES(total_leads),
    total_valid_leads = VALUES(total_valid_leads),
    total_revenue = VALUES(total_revenue),
    last_aggregated_at = VALUES(last_aggregated_at),
    updated_at = VALUES(updated_at)
SQL;
    }

    private function marketingPacketFactsInsertSql(bool $hasCompanyFilter): string
    {
        $companyWhere = $hasCompanyFilter ? ' AND lc.company_id = ? ' : '';

        return <<<SQL
INSERT INTO report_daily_marketing_packet_facts (
    company_id,
    metric_date,
    marketing_source_id,
    parent_marketing_source_id,
    landing_connection_id,
    landing_connection_source_id,
    marketer_user_id,
    team_id,
    ad_channel,
    source_type,
    channel,
    utm_source,
    utm_campaign,
    utm_medium,
    utm_term,
    utm_content,
    status,
    dimension_hash,
    packet_count,
    primary_packet_count,
    upsale_packet_count,
    processed_count,
    rejected_count,
    failed_count,
    no_phone_count,
    phone_count,
    unique_phone_count,
    duplicate_packet_count,
    first_received_at,
    last_received_at,
    created_at,
    updated_at
)
SELECT
    base.company_id,
    base.metric_date,
    base.marketing_source_id,
    base.parent_marketing_source_id,
    base.landing_connection_id,
    base.landing_connection_source_id,
    base.marketer_user_id,
    base.team_id,
    base.ad_channel,
    base.source_type,
    base.channel,
    base.utm_source,
    base.utm_campaign,
    base.utm_medium,
    base.utm_term,
    base.utm_content,
    base.status,
    SHA2(CONCAT_WS('|',
        base.marketing_source_id,
        base.parent_marketing_source_id,
        base.landing_connection_id,
        base.landing_connection_source_id,
        base.marketer_user_id,
        base.team_id,
        base.ad_channel,
        base.source_type,
        base.channel,
        base.utm_source,
        base.utm_campaign,
        base.utm_medium,
        base.utm_term,
        base.utm_content,
        base.status
    ), 256) AS dimension_hash,
    COUNT(*) AS packet_count,
    SUM(CASE WHEN base.is_upsale = 0 THEN 1 ELSE 0 END) AS primary_packet_count,
    SUM(CASE WHEN base.is_upsale = 1 THEN 1 ELSE 0 END) AS upsale_packet_count,
    SUM(CASE WHEN base.status = 'processed' THEN 1 ELSE 0 END) AS processed_count,
    SUM(CASE WHEN base.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
    SUM(CASE WHEN base.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
    SUM(CASE WHEN base.phone = '' THEN 1 ELSE 0 END) AS no_phone_count,
    SUM(CASE WHEN base.phone <> '' THEN 1 ELSE 0 END) AS phone_count,
    COUNT(DISTINCT CASE WHEN base.phone <> '' THEN base.phone ELSE NULL END) AS unique_phone_count,
    GREATEST(0,
        SUM(CASE WHEN base.phone <> '' THEN 1 ELSE 0 END)
        - COUNT(DISTINCT CASE WHEN base.phone <> '' THEN base.phone ELSE NULL END)
    ) AS duplicate_packet_count,
    MIN(base.created_at) AS first_received_at,
    MAX(base.created_at) AS last_received_at,
    NOW() AS created_at,
    NOW() AS updated_at
FROM (
    SELECT
        lc.company_id,
        ? AS metric_date,
        COALESCE(lc.marketing_source_id, 0) AS marketing_source_id,
        COALESCE(ms.parent_id, 0) AS parent_marketing_source_id,
        lc.id AS landing_connection_id,
        lcs.id AS landing_connection_source_id,
        COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id, lc.marketer_user_id, 0) AS marketer_user_id,
        COALESCE(marketer_users.team_id, 0) AS team_id,
        LEFT(COALESCE(NULLIF(ms.ad_channel, ''), NULLIF(parent_sources.ad_channel, ''), NULLIF(lc.ad_channel, ''), ''), 120) AS ad_channel,
        LEFT(COALESCE(NULLIF(lcs.source_type, ''), 'main'), 40) AS source_type,
        LEFT(ie.channel, 120) AS channel,
        LEFT(COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_source')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_source')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_source')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_source')), ''),
            ''
        ), 180) AS utm_source,
        LEFT(COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_campaign')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.campaign')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_campaign')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_campaign')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_campaign')), ''),
            ''
        ), 220) AS utm_campaign,
        LEFT(COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_medium')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_medium')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_medium')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_medium')), ''),
            ''
        ), 180) AS utm_medium,
        LEFT(COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_term')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_term')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_term')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_term')), ''),
            ''
        ), 220) AS utm_term,
        LEFT(COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm_content')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.utm.utm_content')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.tracking.utm_content')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.query.utm_content')), ''),
            ''
        ), 220) AS utm_content,
        LEFT(COALESCE(NULLIF(ie.status, ''), 'unknown'), 30) AS status,
        LEFT(REGEXP_REPLACE(COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.phone')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.customer_phone')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.sdt')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$."Số điện thoại"')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$."số điện thoại"')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.form.phone')), ''),
            ''
        ), '[^0-9]', ''), 20) AS phone,
        CASE
            WHEN LOWER(COALESCE(lcs.source_type, 'main')) IN ('upsell', 'upsale', 'supplement', 'thank_you') THEN 1
            WHEN LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.item_type')), '')) IN ('upsell', 'upsale', 'late_upsell', 'late_upsale') THEN 1
            WHEN LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.landing_source_type')), '')) IN ('upsell', 'upsale', 'late_upsell', 'late_upsale') THEN 1
            WHEN LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.is_upsell')), '')) IN ('1', 'true', 'yes', 'on') THEN 1
            WHEN LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ie.payload, '$.is_upsale')), '')) IN ('1', 'true', 'yes', 'on') THEN 1
            ELSE 0
        END AS is_upsale,
        ie.created_at
    FROM inbound_events ie
    INNER JOIN landing_connection_sources lcs
        ON ie.channel = CONCAT('landing-connection:', lcs.landing_connection_id, ':source:', lcs.id)
    INNER JOIN landing_connections lc ON lc.id = lcs.landing_connection_id
    LEFT JOIN marketing_sources ms ON ms.id = lc.marketing_source_id
    LEFT JOIN marketing_sources parent_sources ON parent_sources.id = ms.parent_id
    LEFT JOIN users marketer_users ON marketer_users.id = COALESCE(ms.marketer_user_id, parent_sources.marketer_user_id, lc.marketer_user_id)
    WHERE ie.source = 'landing_webhook'
      AND ie.created_at >= ?
      AND ie.created_at < ?
      {$companyWhere}
      AND lcs.deleted_at IS NULL
      AND lc.deleted_at IS NULL
) base
GROUP BY
    base.company_id,
    base.metric_date,
    base.marketing_source_id,
    base.parent_marketing_source_id,
    base.landing_connection_id,
    base.landing_connection_source_id,
    base.marketer_user_id,
    base.team_id,
    base.ad_channel,
    base.source_type,
    base.channel,
    base.utm_source,
    base.utm_campaign,
    base.utm_medium,
    base.utm_term,
    base.utm_content,
    base.status
ON DUPLICATE KEY UPDATE
    packet_count = VALUES(packet_count),
    primary_packet_count = VALUES(primary_packet_count),
    upsale_packet_count = VALUES(upsale_packet_count),
    processed_count = VALUES(processed_count),
    rejected_count = VALUES(rejected_count),
    failed_count = VALUES(failed_count),
    no_phone_count = VALUES(no_phone_count),
    phone_count = VALUES(phone_count),
    unique_phone_count = VALUES(unique_phone_count),
    duplicate_packet_count = VALUES(duplicate_packet_count),
    first_received_at = VALUES(first_received_at),
    last_received_at = VALUES(last_received_at),
    updated_at = VALUES(updated_at)
SQL;
    }
}
