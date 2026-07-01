<?php

namespace App\Contracts\Shipping;

use Carbon\Carbon;

interface CarrierSettlementAdapterInterface
{
    public function provider(): string;

    /**
     * Lấy dòng đối soát COD từ API hãng trong kỳ.
     *
     * @return list<array{
     *   tracking_number?:?string,
     *   partner_order_code?:?string,
     *   cod_amount:int,
     *   carrier_fee?:int,
     *   net_amount?:int,
     *   transaction_code?:?string,
     *   settled_at?:?string,
     *   raw_payload?:?array
     * }>
     */
    public function fetchSettlementLines(Carbon $from, Carbon $to): array;
}
