<?php

namespace App\Console\Commands;

use App\Services\Shipping\Settlement\CarrierSettlementAdapterRegistry;
use App\Services\Shipping\Settlement\CarrierSettlementApiSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCarrierSettlementCommand extends Command
{
    protected $signature = 'shipping:settlement-sync
        {provider : viettel_post|ghtk}
        {--from= : YYYY-MM-DD}
        {--to= : YYYY-MM-DD}';

    protected $description = 'Đồng bộ dòng tiền COD từ API/webhook paid của hãng vận chuyển';

    public function handle(
        CarrierSettlementAdapterRegistry $registry,
        CarrierSettlementApiSyncService $sync,
    ): int {
        $provider = (string) $this->argument('provider');

        if (! in_array($provider, $registry->providers(), true)) {
            $this->error("Provider [{$provider}] chưa hỗ trợ settlement sync.");

            return self::FAILURE;
        }

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : now()->endOfDay();

        $result = $sync->syncProvider($provider, $from, $to);

        $this->info(sprintf(
            'Đã sync %s: %d dòng (%d match, %d unmatched).',
            $provider,
            $result['lines_total'],
            $result['lines_matched'],
            $result['lines_unmatched'],
        ));

        return self::SUCCESS;
    }
}
