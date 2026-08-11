<?php

namespace App\Services\Reporting;

use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Models\Reporting\ReportDailyClosure;
use App\Support\OrderRevenue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DailyReportAggregator
{
    /** @return array<string,string> date_basis => orders column */
    private function orderDateBases(): array
    {
        $bases = [];
        foreach (DateType::cases() as $dateType) {
            $bases[$dateType->value] = $dateType->orderColumn();
        }

        return $bases;
    }

    public function __construct(
        private readonly ReportDateDirtyTracker $dirtyTracker,
    ) {}

    /** @return array<string,mixed> */
    public function rebuild(int $companyId, CarbonInterface|string $date, bool $finalize = false): array
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->timezone(config('reporting.timezone'))->startOfDay()
            : CarbonImmutable::parse($date, config('reporting.timezone'))->startOfDay();

        $lock = Cache::lock("reporting:aggregate:{$companyId}:{$day->toDateString()}", 900);
        if (! $lock->get()) {
            throw new RuntimeException("Report aggregation is already running for company {$companyId} on {$day->toDateString()}.");
        }

        $closure = ReportDailyClosure::query()->firstOrCreate(
            ['company_id' => $companyId, 'metric_date' => $day->toDateString()],
            ['status' => 'open', 'revision' => 1],
        );

        try {
            $closure->update(['status' => 'finalizing', 'last_error' => null]);
            $sourceChecksumBefore = $finalize ? $this->sourceChecksum($companyId, $day) : null;

            $result = DB::transaction(function () use ($companyId, $day): array {
                $this->deleteExisting($companyId, $day);

                $leadRows = $this->aggregateLeads($companyId, $day);
                $marketingPacketRows = $this->aggregateMarketingRawPackets($companyId, $day);
                $orderRows = $this->aggregateOrders($companyId, $day);
                $productRows = $this->aggregateProducts($companyId, $day);
                $cashflowRows = $this->aggregateCashflow($companyId, $day);
                $inventoryRows = $this->aggregateInventory($companyId, $day);

                return compact('leadRows', 'marketingPacketRows', 'orderRows', 'productRows', 'cashflowRows', 'inventoryRows');
            }, 3);

            $sourceChecksum = $this->sourceChecksum($companyId, $day);
            if ($finalize && $sourceChecksumBefore !== null && ! hash_equals($sourceChecksumBefore, $sourceChecksum)) {
                throw new RuntimeException('Source rows changed while finalizing the reporting day; retry is required.');
            }

            $factsChecksum = $this->factsChecksum($companyId, $day);
            $watermark = $this->sourceWatermark($companyId, $day);

            $closure->refresh();
            $closure->update([
                'status' => $finalize ? 'closed' : 'open',
                'revision' => ((int) $closure->revision) + 1,
                'lead_rows' => $result['leadRows'],
                'marketing_packet_rows' => $result['marketingPacketRows'],
                'order_rows' => $result['orderRows'],
                'product_rows' => $result['productRows'],
                'cashflow_rows' => $result['cashflowRows'],
                'inventory_rows' => $result['inventoryRows'],
                'source_checksum' => $sourceChecksum,
                'facts_checksum' => $factsChecksum,
                'source_watermark_at' => $watermark,
                'last_rebuilt_at' => now(),
                'finalized_at' => $finalize ? now() : null,
                'last_error' => null,
            ]);

            DB::table('report_dirty_dates')
                ->where('company_id', $companyId)
                ->whereDate('metric_date', $day->toDateString())
                ->delete();

            $this->dirtyTracker->invalidateSnapshots($companyId, $day);
            $this->dirtyTracker->bumpCompanyRevision($companyId);

            return array_merge($result, [
                'companyId' => $companyId,
                'date' => $day->toDateString(),
                'status' => $finalize ? 'closed' : 'open',
                'sourceChecksum' => $sourceChecksum,
                'factsChecksum' => $factsChecksum,
            ]);
        } catch (Throwable $e) {
            $closure->update([
                'status' => 'error',
                'last_error' => mb_substr($e->getMessage(), 0, 65535),
                'last_rebuilt_at' => now(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function deleteExisting(int $companyId, CarbonImmutable $day): void
    {
        foreach ([
            'report_daily_lead_facts',
            'report_daily_marketing_packet_facts',
            'report_daily_order_facts',
            'report_daily_product_facts',
            'report_daily_cashflow_facts',
            'report_daily_inventory_facts',
        ] as $table) {
            DB::table($table)
                ->where('company_id', $companyId)
                ->whereDate('metric_date', $day->toDateString())
                ->delete();
        }
    }


    private function aggregateMarketingRawPackets(int $companyId, CarbonImmutable $day): int
    {
        return app(SqlMarketingFactAggregator::class)->aggregateMarketingPacketFactsDate($day, $companyId);
    }

    private function aggregateLeads(int $companyId, CarbonImmutable $day): int
    {
        $rows = DB::table('lead_ingestions as li')
            ->leftJoin('orders as o', 'o.id', '=', 'li.order_id')
            ->where('li.company_id', $companyId)
            ->whereBetween('li.created_at', [$day, $day->endOfDay()])
            ->selectRaw("COALESCE(li.platform, '') as platform")
            ->selectRaw("COALESCE(li.status, '') as status")
            ->selectRaw("COALESCE(li.packet_type, '') as packet_type")
            ->selectRaw('COALESCE(li.marketing_source_id, 0) as marketing_source_id')
            ->selectRaw('COALESCE(li.landing_connection_id, 0) as landing_connection_id')
            ->selectRaw('COALESCE(li.landing_connection_source_id, 0) as landing_connection_source_id')
            ->selectRaw('COALESCE(o.sale_user_id, 0) as sale_user_id')
            ->selectRaw('COALESCE(o.marketer_user_id, 0) as marketer_user_id')
            ->selectRaw('COALESCE(o.team_id, 0) as team_id')
            ->selectRaw('COALESCE(o.warehouse_id, 0) as warehouse_id')
            ->selectRaw("COALESCE(o.delivery_status, '') as delivery_status")
            ->selectRaw("COALESCE(o.reconciliation_status, '') as reconciliation_status")
            ->selectRaw('COUNT(*) as packet_count')
            // lead_count khớp LeadContactMetrics::countableQuery (loại duplicate/needs_review/failed).
            ->selectRaw("SUM(CASE WHEN li.counts_as_lead = 1 AND li.status NOT IN ('duplicate','needs_review','failed') THEN 1 ELSE 0 END) as lead_count")
            ->selectRaw("SUM(CASE WHEN li.counts_as_lead = 1 AND li.status = 'processed' THEN 1 ELSE 0 END) as processed_count")
            ->selectRaw("SUM(CASE WHEN li.counts_as_lead = 1 AND li.status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN li.counts_as_lead = 1 AND li.status = 'duplicate' THEN 1 ELSE 0 END) as duplicate_count")
            ->selectRaw('SUM(CASE WHEN li.requires_review = 1 AND li.reviewed_at IS NULL THEN 1 ELSE 0 END) as review_count')
            ->groupBy([
                'li.platform', 'li.status', 'li.packet_type', 'li.marketing_source_id',
                'li.landing_connection_id', 'li.landing_connection_source_id',
                'o.sale_user_id', 'o.marketer_user_id', 'o.team_id', 'o.warehouse_id',
                'o.delivery_status', 'o.reconciliation_status',
            ])
            ->get();

        return $this->insertFactRows('report_daily_lead_facts', $companyId, $day, $rows, [
            'platform', 'status', 'packet_type', 'marketing_source_id', 'landing_connection_id',
            'landing_connection_source_id', 'sale_user_id', 'marketer_user_id', 'team_id',
            'warehouse_id', 'delivery_status', 'reconciliation_status',
        ]);
    }

    private function aggregateOrders(int $companyId, CarbonImmutable $day): int
    {
        $inserted = 0;
        $eligible = $this->quotedList(DeliveryStatus::revenueEligible());
        $gross = OrderRevenue::grossAmountSql('o');
        $shippingCost = OrderRevenue::shippingCostSql('o');
        $net = OrderRevenue::netAmountSql('o');
        $provider = "COALESCE(NULLIF(o.shipping_provider, ''), NULLIF(o.carrier_name, ''), '')";

        foreach ($this->orderDateBases() as $dateBasis => $column) {
            $rows = DB::table('orders as o')
                ->where('o.company_id', $companyId)
                ->whereBetween("o.{$column}", [$day, $day->endOfDay()])
                ->selectRaw('COALESCE(o.sale_user_id, 0) as sale_user_id')
                ->selectRaw('COALESCE(o.marketer_user_id, 0) as marketer_user_id')
                ->selectRaw('COALESCE(o.team_id, 0) as team_id')
                ->selectRaw('COALESCE(o.marketing_source_id, 0) as marketing_source_id')
                ->selectRaw('COALESCE(o.landing_connection_id, 0) as landing_connection_id')
                ->selectRaw('COALESCE(o.warehouse_id, 0) as warehouse_id')
                ->selectRaw("{$provider} as shipping_provider")
                ->selectRaw("COALESCE(o.shipping_method, '') as shipping_method")
                ->selectRaw("CASE WHEN COALESCE(o.is_returning_customer, 0) = 1 THEN 'old' ELSE 'new' END as customer_type")
                ->selectRaw("CASE WHEN COALESCE(o.is_duplicate_phone, 0) = 1 THEN 'duplicate' ELSE 'unique' END as duplicate_phone_status")
                ->selectRaw("COALESCE(o.warehouse_care_status, '') as warehouse_care_status")
                ->selectRaw("CASE WHEN o.printed_at IS NULL THEN 'not_printed' ELSE 'printed' END as printed_status")
                ->selectRaw("CASE WHEN COALESCE(o.deposit, 0) > 0 THEN 'with_deposit' ELSE 'without_deposit' END as deposit_status")
                ->selectRaw("COALESCE(o.delivery_status, '') as delivery_status")
                ->selectRaw("COALESCE(o.reconciliation_status, '') as reconciliation_status")
                ->selectRaw("COALESCE(o.operation_stage, '') as operation_stage")
                ->selectRaw("COALESCE(o.operation_result, '') as operation_result")
                ->selectRaw("COALESCE(o.closing_status, '') as closing_status")
                ->selectRaw('COUNT(*) as order_count')
                ->selectRaw('SUM(CASE WHEN o.closed_at IS NOT NULL THEN 1 ELSE 0 END) as closed_order_count')
                ->selectRaw('SUM(CASE WHEN o.closed_at IS NULL THEN 1 ELSE 0 END) as open_order_count')
                ->selectRaw("SUM(CASE WHEN o.delivery_status IN ({$eligible}) THEN 1 ELSE 0 END) as delivered_order_count")
                ->selectRaw("SUM(CASE WHEN o.delivery_status IN ('partial','partial_delivery','delivered_partial','partially_delivered') THEN 1 ELSE 0 END) as partial_delivery_count")
                ->selectRaw("SUM(CASE WHEN o.delivery_status IN ('returned','refund') THEN 1 ELSE 0 END) as returned_order_count")
                ->selectRaw("SUM(CASE WHEN o.delivery_status IN ('cancel_waybill','cancel_closing') THEN 1 ELSE 0 END) as cancelled_order_count")
                ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND (COALESCE(oi2.origin,'') IN ('upsell','late_upsell','orphan_upsell') OR COALESCE(oi2.item_type,'') = 'upsell')) THEN 1 ELSE 0 END) as upsell_order_count")
                ->selectRaw('SUM(COALESCE(o.contact_count, 0)) as contact_count')
                ->selectRaw('SUM(CASE WHEN COALESCE(o.contact_count, 0) > 0 THEN 1 ELSE 0 END) as contacted_order_count')
                ->selectRaw('SUM(COALESCE(o.subtotal, 0)) as gross_sales')
                ->selectRaw('SUM(COALESCE(o.discount, 0)) as discount_amount')
                ->selectRaw('SUM(COALESCE(o.vat, 0)) as vat_amount')
                ->selectRaw('SUM(COALESCE(o.shipping_fee_collected, 0)) as shipping_collected')
                ->selectRaw("SUM({$gross}) as order_value")
                ->selectRaw("SUM(CASE WHEN o.delivery_status IN ({$eligible}) THEN {$net} ELSE 0 END) as recognized_revenue")
                ->selectRaw('SUM(COALESCE(o.deposit, 0)) as deposit_amount')
                ->selectRaw('SUM(COALESCE(o.amount_to_collect, 0)) as amount_to_collect')
                ->selectRaw('SUM(COALESCE(o.settled_cod_amount, 0)) as settled_cod_amount')
                ->selectRaw("SUM({$shippingCost}) as shipping_cost")
                ->selectRaw("SUM(CASE WHEN o.closed_at IS NOT NULL THEN {$shippingCost} ELSE 0 END) as closed_shipping_cost")
                ->selectRaw('SUM(COALESCE(o.carrier_return_fee, 0)) as return_fee')
                ->selectRaw('SUM(COALESCE(o.carrier_compensation_amount, 0)) as compensation_amount')
                ->selectRaw("SUM(COALESCE(o.settled_cod_amount, 0) + COALESCE(o.deposit, 0) - ({$shippingCost})) as net_cashflow")
                ->groupBy([
                    'o.sale_user_id', 'o.marketer_user_id', 'o.team_id', 'o.marketing_source_id',
                    'o.landing_connection_id', 'o.warehouse_id', 'o.shipping_method',
                    'o.is_returning_customer', 'o.is_duplicate_phone', 'o.warehouse_care_status', 'o.printed_at',
                    'o.delivery_status', 'o.reconciliation_status', 'o.operation_stage', 'o.operation_result', 'o.closing_status',
                ])
                ->groupByRaw($provider)
                ->get()
                ->map(function ($row) use ($dateBasis) {
                    $row->date_basis = $dateBasis;

                    return $row;
                });

            $inserted += $this->insertFactRows('report_daily_order_facts', $companyId, $day, $rows, [
                'date_basis', 'sale_user_id', 'marketer_user_id', 'team_id', 'marketing_source_id',
                'landing_connection_id', 'warehouse_id', 'shipping_provider', 'shipping_method',
                'customer_type', 'duplicate_phone_status', 'warehouse_care_status', 'printed_status', 'deposit_status',
                'delivery_status', 'reconciliation_status', 'operation_stage', 'operation_result', 'closing_status',
            ], ['date_basis' => $dateBasis]);
        }

        return $inserted;
    }

    private function aggregateProducts(int $companyId, CarbonImmutable $day): int
    {
        $inserted = 0;
        $eligible = $this->quotedList(DeliveryStatus::revenueEligible());
        $upsellExpr = "CASE WHEN COALESCE(oi.origin,'') IN ('upsell','late_upsell','orphan_upsell') OR COALESCE(oi.item_type,'') = 'upsell' THEN 1 ELSE 0 END";
        $lineGross = '(COALESCE(oi.unit_price,0) * COALESCE(oi.quantity,0))';
        $lineNet = "CASE WHEN ({$lineGross} - COALESCE(oi.discount_amount,0)) < 0 THEN 0 ELSE ({$lineGross} - COALESCE(oi.discount_amount,0)) END";

        foreach ($this->orderDateBases() as $dateBasis => $column) {
            $rows = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
                ->where('o.company_id', $companyId)
                ->whereBetween("o.{$column}", [$day, $day->endOfDay()])
                ->selectRaw('COALESCE(oi.product_id, 0) as product_id')
                ->selectRaw('COALESCE(p.parent_id, 0) as parent_product_id')
                ->selectRaw('COALESCE(o.sale_user_id, 0) as sale_user_id')
                ->selectRaw('COALESCE(o.marketer_user_id, 0) as marketer_user_id')
                ->selectRaw('COALESCE(o.team_id, 0) as team_id')
                ->selectRaw('COALESCE(o.marketing_source_id, 0) as marketing_source_id')
                ->selectRaw('COALESCE(o.landing_connection_id, 0) as landing_connection_id')
                ->selectRaw('COALESCE(o.warehouse_id, 0) as warehouse_id')
                ->selectRaw("COALESCE(oi.origin, '') as item_origin")
                ->selectRaw("{$upsellExpr} as is_upsell")
                ->selectRaw("COALESCE(o.delivery_status, '') as delivery_status")
                ->selectRaw("COALESCE(o.reconciliation_status, '') as reconciliation_status")
                ->selectRaw('COUNT(DISTINCT o.id) as order_count')
                ->selectRaw('COUNT(*) as line_count')
                ->selectRaw('SUM(COALESCE(oi.quantity, 0)) as quantity')
                ->selectRaw("SUM({$lineGross}) as gross_sales")
                ->selectRaw('SUM(COALESCE(oi.discount_amount, 0)) as discount_amount')
                ->selectRaw("SUM({$lineNet}) as net_sales")
                ->selectRaw('SUM(COALESCE(oi.cost_price, 0) * COALESCE(oi.quantity, 0)) as cost_of_goods')
                ->selectRaw("SUM(CASE WHEN o.delivery_status IN ({$eligible}) THEN {$lineNet} ELSE 0 END) as recognized_revenue")
                ->groupBy([
                    'oi.product_id', 'p.parent_id', 'o.sale_user_id', 'o.marketer_user_id', 'o.team_id',
                    'o.marketing_source_id', 'o.landing_connection_id', 'o.warehouse_id',
                    'oi.origin', 'o.delivery_status', 'o.reconciliation_status',
                ])
                ->groupByRaw($upsellExpr)
                ->get()
                ->map(function ($row) use ($dateBasis) {
                    $row->date_basis = $dateBasis;
                    $row->is_upsell = (int) $row->is_upsell;

                    return $row;
                });

            $inserted += $this->insertFactRows('report_daily_product_facts', $companyId, $day, $rows, [
                'date_basis', 'product_id', 'parent_product_id', 'sale_user_id', 'marketer_user_id', 'team_id',
                'marketing_source_id', 'landing_connection_id', 'warehouse_id', 'item_origin',
                'is_upsell', 'delivery_status', 'reconciliation_status',
            ], ['date_basis' => $dateBasis]);
        }

        return $inserted;
    }

    private function aggregateCashflow(int $companyId, CarbonImmutable $day): int
    {
        $inserted = 0;
        $bases = [
            'delivered' => ['column' => 's.delivered_at', 'codExpected' => true, 'codCollected' => true, 'fees' => true],
            'returned' => ['column' => 's.returned_at', 'return' => true],
            'cod_remitted' => ['column' => 's.cod_remitted_at', 'codRemitted' => true, 'codFee' => true],
        ];

        foreach ($bases as $basis => $definition) {
            $rows = DB::table('shipments as s')
                ->join('orders as o', 'o.id', '=', 's.order_id')
                ->where('s.company_id', $companyId)
                ->whereBetween($definition['column'], [$day, $day->endOfDay()])
                ->selectRaw('COALESCE(o.marketing_source_id, 0) as marketing_source_id')
                ->selectRaw('COALESCE(o.warehouse_id, 0) as warehouse_id')
                ->selectRaw("COALESCE(s.provider, '') as shipping_provider")
                ->selectRaw('COUNT(*) as shipment_count')
                ->selectRaw('0 as cod_mismatch_count')
                ->selectRaw(($definition['codExpected'] ?? false) ? 'SUM(COALESCE(s.cod_amount,0)) as cod_expected' : '0 as cod_expected')
                ->selectRaw(($definition['codCollected'] ?? false) ? 'SUM(COALESCE(s.cod_collected,0)) as cod_collected' : '0 as cod_collected')
                ->selectRaw(($definition['codRemitted'] ?? false) ? 'SUM(COALESCE(s.cod_remitted,0)) as cod_remitted' : '0 as cod_remitted')
                ->selectRaw(($definition['fees'] ?? false) ? 'SUM(COALESCE(s.fee,0)) as shipping_fee' : '0 as shipping_fee')
                ->selectRaw(($definition['return'] ?? false) ? 'SUM(COALESCE(s.return_fee,0)) as return_fee' : '0 as return_fee')
                ->selectRaw(($definition['codFee'] ?? false) ? 'SUM(COALESCE(s.cod_fee,0)) as cod_fee' : '0 as cod_fee')
                ->selectRaw(($definition['fees'] ?? false) ? 'SUM(COALESCE(s.insurance_fee,0)) as insurance_fee' : '0 as insurance_fee')
                ->selectRaw(($definition['fees'] ?? false) ? 'SUM(COALESCE(s.other_fee,0)) as other_fee' : '0 as other_fee')
                ->selectRaw(($definition['return'] ?? false) ? 'SUM(COALESCE(s.compensation_amount,0)) as compensation_amount' : '0 as compensation_amount')
                ->groupBy(['o.marketing_source_id', 'o.warehouse_id', 's.provider'])
                ->get()
                ->map(function ($row) use ($basis) {
                    $row->event_basis = $basis;
                    $row->net_cashflow = (int) $row->cod_remitted
                        - (int) $row->shipping_fee
                        - (int) $row->return_fee
                        - (int) $row->cod_fee
                        - (int) $row->insurance_fee
                        - (int) $row->other_fee
                        + (int) $row->compensation_amount;

                    return $row;
                });

            $inserted += $this->insertFactRows('report_daily_cashflow_facts', $companyId, $day, $rows, [
                'event_basis', 'marketing_source_id', 'warehouse_id', 'shipping_provider',
            ], ['event_basis' => $basis]);
        }

        $webhookRows = DB::table('shipping_webhook_events as swe')
            ->leftJoin('orders as o', 'o.id', '=', 'swe.order_id')
            ->where('swe.company_id', $companyId)
            ->whereBetween(DB::raw('COALESCE(swe.occurred_at, swe.received_at, swe.created_at)'), [$day, $day->endOfDay()])
            ->selectRaw('COALESCE(o.marketing_source_id, 0) as marketing_source_id')
            ->selectRaw('COALESCE(o.warehouse_id, 0) as warehouse_id')
            ->selectRaw("COALESCE(swe.provider, '') as shipping_provider")
            ->selectRaw('0 as shipment_count')
            ->selectRaw('SUM(CASE WHEN swe.is_cod_mismatch = 1 THEN 1 ELSE 0 END) as cod_mismatch_count')
            ->selectRaw('0 as cod_expected, 0 as cod_collected, 0 as cod_remitted')
            ->selectRaw('SUM(COALESCE(swe.shipping_fee,0)) as shipping_fee')
            ->selectRaw('SUM(COALESCE(swe.return_fee,0)) as return_fee')
            ->selectRaw('SUM(COALESCE(swe.cod_fee,0)) as cod_fee')
            ->selectRaw('0 as insurance_fee')
            ->selectRaw('SUM(COALESCE(swe.other_fee,0)) as other_fee')
            ->selectRaw('SUM(COALESCE(swe.compensation_amount,0)) as compensation_amount')
            ->groupBy(['o.marketing_source_id', 'o.warehouse_id', 'swe.provider'])
            ->get()
            ->map(function ($row) {
                $row->event_basis = 'webhook';
                $row->net_cashflow = 0;
                return $row;
            });

        $inserted += $this->insertFactRows('report_daily_cashflow_facts', $companyId, $day, $webhookRows, [
            'event_basis', 'marketing_source_id', 'warehouse_id', 'shipping_provider',
        ], ['event_basis' => 'webhook']);

        return $inserted;
    }

    private function aggregateInventory(int $companyId, CarbonImmutable $day): int
    {
        $rows = DB::table('warehouse_inventory_movements as m')
            ->leftJoin('products as p', 'p.id', '=', 'm.product_id')
            ->where('m.company_id', $companyId)
            ->whereBetween('m.created_at', [$day, $day->endOfDay()])
            ->selectRaw('COALESCE(m.warehouse_id, 0) as warehouse_id')
            ->selectRaw('COALESCE(m.product_id, 0) as product_id')
            ->selectRaw("COALESCE(m.type, '') as movement_type")
            ->selectRaw('COUNT(*) as movement_count')
            ->selectRaw("SUM(CASE WHEN m.type IN ('intake','return') OR m.quantity > 0 THEN ABS(m.quantity) ELSE 0 END) as quantity_in")
            ->selectRaw("SUM(CASE WHEN m.type IN ('export','deduction') OR m.quantity < 0 THEN ABS(m.quantity) ELSE 0 END) as quantity_out")
            ->selectRaw('SUM(COALESCE(m.quantity,0)) as quantity_net')
            ->selectRaw("SUM(CASE WHEN m.type IN ('intake','return') OR m.quantity > 0 THEN ABS(m.quantity) * COALESCE(NULLIF(m.unit_cost,0),p.cost_price,0) ELSE 0 END) as value_in")
            ->selectRaw("SUM(CASE WHEN m.type IN ('export','deduction') OR m.quantity < 0 THEN ABS(m.quantity) * COALESCE(NULLIF(m.unit_cost,0),p.cost_price,0) ELSE 0 END) as value_out")
            ->groupBy(['m.warehouse_id', 'm.product_id', 'm.type'])
            ->get();

        return $this->insertFactRows('report_daily_inventory_facts', $companyId, $day, $rows, [
            'warehouse_id', 'product_id', 'movement_type',
        ]);
    }

    /**
     * @param Collection<int,object> $rows
     * @param list<string> $dimensionColumns
     * @param array<string,mixed> $forced
     */
    private function insertFactRows(
        string $table,
        int $companyId,
        CarbonImmutable $day,
        Collection $rows,
        array $dimensionColumns,
        array $forced = [],
    ): int {
        if ($rows->isEmpty()) {
            return 0;
        }

        $now = now();
        $payload = $rows->map(function (object $row) use ($companyId, $day, $dimensionColumns, $forced, $now): array {
            $record = array_merge((array) $row, $forced);
            $dimensions = [];
            foreach ($dimensionColumns as $column) {
                $dimensions[$column] = $record[$column] ?? 0;
            }

            $record['company_id'] = $companyId;
            $record['metric_date'] = $day->toDateString();
            $record['dimension_hash'] = hash('sha256', json_encode($dimensions, JSON_THROW_ON_ERROR));
            $record['created_at'] = $now;
            $record['updated_at'] = $now;

            foreach ($record as $key => $value) {
                if (is_numeric($value) && ! in_array($key, ['shipping_provider', 'shipping_method', 'delivery_status', 'status', 'platform', 'packet_type', 'date_basis', 'event_basis', 'operation_stage', 'operation_result', 'closing_status', 'reconciliation_status', 'item_origin', 'movement_type', 'customer_type', 'duplicate_phone_status', 'warehouse_care_status', 'printed_status', 'deposit_status'], true)) {
                    $record[$key] = (int) $value;
                }
            }

            return $record;
        })->all();

        // COALESCE dimensions can occasionally produce two SQL groups with the same normalized
        // value (for example NULL and empty string). Collapse them before hitting the unique key.
        $dimensionSet = array_fill_keys($dimensionColumns, true);
        $payload = collect($payload)
            ->groupBy('dimension_hash')
            ->map(function (Collection $group) use ($dimensionSet): array {
                $base = $group->first();
                foreach ($group->slice(1) as $duplicate) {
                    foreach ($duplicate as $key => $value) {
                        if (isset($dimensionSet[$key])
                            || in_array($key, ['company_id', 'metric_date', 'dimension_hash', 'created_at', 'updated_at'], true)
                            || ! is_numeric($value)) {
                            continue;
                        }

                        $base[$key] = (int) ($base[$key] ?? 0) + (int) $value;
                    }
                }

                return $base;
            })
            ->values()
            ->all();

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        return count($payload);
    }


    /** @return array<string,mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** @param array<string,mixed> $payload @param list<string> $keys */
    private function packetPayloadText(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /** @param array<string,mixed> $payload */
    private function packetPhone(array $payload): string
    {
        $raw = $this->packetPayloadText($payload, ['phone', 'customer_phone', 'sdt', 'Số điện thoại', 'số điện thoại', 'form.phone']);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits !== '' ? mb_substr($digits, 0, 20) : '';
    }

    /** @param array<string,mixed> $payload */
    private function urlQueryValue(array $payload, string $key): string
    {
        $url = $this->packetPayloadText($payload, ['url_page', 'link', 'url', 'source_url', 'page_url', 'landing_url']);
        if ($url === '') {
            return '';
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        $value = $params[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string,mixed> $payload */
    private function isMarketingUpsalePacket(array $payload, string $sourceType): bool
    {
        $normalizedSourceType = strtolower($sourceType);
        if (in_array($normalizedSourceType, ['upsell', 'upsale', 'supplement', 'thank_you'], true)) {
            return true;
        }

        $payloadType = strtolower($this->packetPayloadText($payload, ['item_type', 'landing_source_type']));
        $isUpsell = strtolower($this->packetPayloadText($payload, ['is_upsell', 'is_upsale']));

        return in_array($payloadType, ['upsell', 'upsale', 'late_upsell', 'late_upsale'], true)
            || in_array($isUpsell, ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string,mixed> */
    public function verify(int $companyId, CarbonInterface|string $date): array
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->timezone(config('reporting.timezone'))->startOfDay()
            : CarbonImmutable::parse($date, config('reporting.timezone'))->startOfDay();
        $closure = ReportDailyClosure::query()
            ->where('company_id', $companyId)
            ->whereDate('metric_date', $day->toDateString())
            ->first();

        if (! $closure) {
            return ['valid' => false, 'reason' => 'closure_missing'];
        }

        $sourceChecksum = $this->sourceChecksum($companyId, $day);
        $factsChecksum = $this->factsChecksum($companyId, $day);

        return [
            'valid' => hash_equals((string) $closure->source_checksum, $sourceChecksum)
                && hash_equals((string) $closure->facts_checksum, $factsChecksum),
            'sourceValid' => hash_equals((string) $closure->source_checksum, $sourceChecksum),
            'factsValid' => hash_equals((string) $closure->facts_checksum, $factsChecksum),
            'storedSourceChecksum' => $closure->source_checksum,
            'currentSourceChecksum' => $sourceChecksum,
            'storedFactsChecksum' => $closure->facts_checksum,
            'currentFactsChecksum' => $factsChecksum,
            'status' => $closure->status,
        ];
    }

    private function sourceChecksum(int $companyId, CarbonImmutable $day): string
    {
        $orderDateColumns = collect($this->orderDateBases())->values()
            ->map(fn (string $column) => 'o.'.$column)
            ->all();

        $queries = [
            'lead_ingestions' => [
                DB::table('lead_ingestions as li')
                    ->where('li.company_id', $companyId)
                    ->whereBetween('li.created_at', [$day, $day->endOfDay()]),
                'li.id', 'li.updated_at',
            ],
            'inbound_events_landing' => [
                DB::table('inbound_events as ie')
                    ->where('ie.company_id', $companyId)
                    ->where('ie.source', 'landing_webhook')
                    ->whereBetween('ie.created_at', [$day, $day->endOfDay()]),
                'ie.id', 'ie.updated_at',
            ],
            'orders' => [
                $this->whereAnyDate(
                    DB::table('orders as o')->where('o.company_id', $companyId),
                    $orderDateColumns,
                    $day,
                ),
                'o.id', 'o.updated_at',
            ],
            'order_items' => [
                $this->whereAnyDate(
                    DB::table('order_items as oi')
                        ->join('orders as o', 'o.id', '=', 'oi.order_id')
                        ->where('o.company_id', $companyId),
                    $orderDateColumns,
                    $day,
                ),
                'oi.id', 'oi.updated_at',
            ],
            'shipments' => [
                $this->whereAnyDate(
                    DB::table('shipments as s')
                        ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
                        ->where('s.company_id', $companyId),
                    array_merge([
                        's.delivered_at', 's.returned_at', 's.cod_remitted_at', 's.updated_at',
                    ], $orderDateColumns),
                    $day,
                ),
                's.id', 's.updated_at',
            ],
            'shipping_webhook_events' => [
                $this->whereAnyDate(
                    DB::table('shipping_webhook_events as swe')->where('swe.company_id', $companyId),
                    ['swe.occurred_at', 'swe.received_at', 'swe.created_at'],
                    $day,
                ),
                'swe.id', 'swe.updated_at',
            ],
            'warehouse_inventory_movements' => [
                DB::table('warehouse_inventory_movements as m')
                    ->where('m.company_id', $companyId)
                    ->whereBetween('m.created_at', [$day, $day->endOfDay()]),
                'm.id', 'm.updated_at',
            ],
        ];

        $hash = hash_init('sha256');
        foreach ($queries as $name => [$query, $idColumn, $updatedColumn]) {
            hash_update($hash, $name."\n");
            foreach ($query
                ->selectRaw("{$idColumn} as source_id")
                ->selectRaw("{$updatedColumn} as source_updated_at")
                ->orderBy($idColumn)
                ->cursor() as $row) {
                hash_update($hash, (string) $row->source_id.'|'.(string) $row->source_updated_at."\n");
            }
        }

        return hash_final($hash);
    }

    private function factsChecksum(int $companyId, CarbonImmutable $day): string
    {
        $hash = hash_init('sha256');
        foreach ([
            'report_daily_lead_facts',
            'report_daily_marketing_packet_facts',
            'report_daily_order_facts',
            'report_daily_product_facts',
            'report_daily_cashflow_facts',
            'report_daily_inventory_facts',
        ] as $table) {
            hash_update($hash, $table."\n");
            foreach (DB::table($table)
                ->where('company_id', $companyId)
                ->whereDate('metric_date', $day->toDateString())
                ->orderBy('dimension_hash')
                ->cursor() as $row) {
                $payload = (array) $row;
                unset($payload['id'], $payload['created_at'], $payload['updated_at']);
                ksort($payload);
                hash_update($hash, json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )."\n");
            }
        }

        return hash_final($hash);
    }

    /** @param list<string> $columns */
    private function whereAnyDate(Builder $query, array $columns, CarbonImmutable $day): Builder
    {
        return $query->where(function (Builder $nested) use ($columns, $day): void {
            foreach ($columns as $column) {
                $nested->orWhereBetween($column, [$day, $day->endOfDay()]);
            }
        });
    }

    private function sourceWatermark(int $companyId, CarbonImmutable $day): ?string
    {
        $values = collect([
            DB::table('lead_ingestions')->where('company_id', $companyId)->whereBetween('created_at', [$day, $day->endOfDay()])->max('updated_at'),
            DB::table('orders')->where('company_id', $companyId)->whereBetween('updated_at', [$day, $day->endOfDay()])->max('updated_at'),
            DB::table('shipments')->where('company_id', $companyId)->whereBetween('updated_at', [$day, $day->endOfDay()])->max('updated_at'),
        ])->filter()->sortDesc();

        return $values->first();
    }

    /** @param list<string> $values */
    private function quotedList(array $values): string
    {
        return collect($values)
            ->map(fn (string $value): string => DB::getPdo()->quote($value))
            ->implode(',');
    }
}
