<?php

namespace App\Services\Shipping\Settlement;

use App\Models\CarrierSettlementBatch;
use Carbon\Carbon;

class CarrierSettlementApiSyncService
{
    public function __construct(
        private readonly CarrierSettlementAdapterRegistry $registry,
        private readonly CarrierSettlementSyncService $sync,
    ) {}

    public function syncProvider(string $provider, Carbon $from, Carbon $to): array
    {
        $adapter = $this->registry->get($provider);
        $rows = $adapter->fetchSettlementLines($from, $to);
        $code = sprintf('api-%s-%s-%s', $provider, $from->format('Ymd'), $to->format('Ymd'));

        $batch = $this->sync->ingestBatch(
            $provider,
            CarrierSettlementBatch::SOURCE_API,
            $code,
            $rows,
            $from,
            $to,
            ['adapter' => $adapter::class],
        );

        $this->sync->reconcilePeriod($provider, $from, $to);

        return [
            'batch_id' => $batch->id,
            'lines_total' => $batch->lines_total,
            'lines_matched' => $batch->lines_matched,
            'lines_unmatched' => $batch->lines_unmatched,
        ];
    }
}
