<?php

namespace App\Services\Shipping\Settlement;

use App\Models\CarrierSettlementBatch;
use App\Models\CarrierSettlementLine;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CarrierSettlementSyncService
{
    public function __construct(
        private readonly CarrierSettlementMatcher $matcher,
        private readonly ShipmentReconciliationEngine $engine,
    ) {}

    /**
     * @param  list<array{
     *   tracking_number?:?string,
     *   partner_order_code?:?string,
     *   cod_amount:int,
     *   carrier_fee?:int,
     *   net_amount?:int,
     *   transaction_code?:?string,
     *   settled_at?:?string,
     *   raw_payload?:?array
     * }>  $rows
     */
    public function ingestBatch(
        string $provider,
        string $source,
        string $settlementCode,
        array $rows,
        ?Carbon $periodFrom = null,
        ?Carbon $periodTo = null,
        ?array $meta = null,
    ): CarrierSettlementBatch {
        return DB::transaction(function () use ($provider, $source, $settlementCode, $rows, $periodFrom, $periodTo, $meta) {
            $batch = CarrierSettlementBatch::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'settlement_code' => $settlementCode,
                ],
                [
                    'source' => $source,
                    'period_from' => $periodFrom,
                    'period_to' => $periodTo,
                    'meta' => $meta,
                    'imported_at' => now(),
                ],
            );

            $matched = 0;
            $unmatched = 0;
            $codTotal = 0;
            $orderIds = [];

            foreach ($rows as $row) {
                $line = $this->upsertLine($batch, $provider, $settlementCode, $row);
                $codTotal += (int) $line->cod_amount;

                if ($line->match_status === CarrierSettlementLine::MATCH_MATCHED) {
                    $matched++;
                    if ($line->order_id) {
                        $orderIds[] = $line->order_id;
                    }
                } else {
                    $unmatched++;
                }
            }

            $batch->update([
                'lines_total' => count($rows),
                'lines_matched' => $matched,
                'lines_unmatched' => $unmatched,
                'cod_total' => $codTotal,
            ]);

            foreach (array_unique($orderIds) as $orderId) {
                $order = Order::query()->find($orderId);
                if ($order) {
                    $this->engine->reconcileOrder($order->fresh(['settlementLines']));
                }
            }

            return $batch->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertLine(
        CarrierSettlementBatch $batch,
        string $provider,
        string $settlementCode,
        array $row,
    ): CarrierSettlementLine {
        $tracking = filled($row['tracking_number'] ?? null) ? (string) $row['tracking_number'] : null;
        $partnerCode = filled($row['partner_order_code'] ?? null) ? (string) $row['partner_order_code'] : null;
        $transactionCode = (string) ($row['transaction_code'] ?? $tracking ?? $partnerCode ?? uniqid('line_', true));

        $codAmount = max(0, (int) ($row['cod_amount'] ?? 0));
        $carrierFee = max(0, (int) ($row['carrier_fee'] ?? 0));
        $netAmount = (int) ($row['net_amount'] ?? max(0, $codAmount - $carrierFee));

        $match = $this->matcher->match($provider, $tracking, $partnerCode);

        $line = CarrierSettlementLine::query()->updateOrCreate(
            [
                'provider' => $provider,
                'settlement_code' => $settlementCode,
                'tracking_number' => $tracking ?? '',
                'transaction_code' => $transactionCode,
            ],
            [
                'batch_id' => $batch->id,
                'order_id' => $match['order']?->id,
                'partner_order_code' => $partnerCode,
                'cod_amount' => $codAmount,
                'carrier_fee' => $carrierFee,
                'net_amount' => $netAmount,
                'match_status' => $match['status'],
                'match_method' => $match['method'],
                'settled_at' => isset($row['settled_at']) ? Carbon::parse($row['settled_at']) : now(),
                'raw_payload' => $row['raw_payload'] ?? $row,
            ],
        );

        return $line;
    }

    /** Đồng bộ lại toàn bộ đơn đã chốt của một hãng trong kỳ. */
    public function reconcilePeriod(string $provider, Carbon $from, Carbon $to): int
    {
        $count = 0;

        Order::query()
            ->whereNotNull('closed_at')
            ->where('shipping_provider', $provider)
            ->whereBetween('closed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('settlementLines')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $this->engine->reconcileOrder($order);
                    $count++;
                }
            });

        return $count;
    }
}
