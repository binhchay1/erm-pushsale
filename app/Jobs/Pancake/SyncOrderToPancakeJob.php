<?php

namespace App\Jobs\Pancake;

use App\Models\Order;
use App\Models\Scopes\ShopScope;
use App\Models\Scopes\TenantScope;
use App\Services\Pancake\PancakeOutboundSyncService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOrderToPancakeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $orderId,
    ) {
        $this->onQueue(config('saleops.queues.pancake_orders', 'pancake-orders'));
    }

    public function handle(PancakeOutboundSyncService $syncService): void
    {
        $order = Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->withoutGlobalScope(ShopScope::class)
            ->find($this->orderId);

        if (! $order) {
            return;
        }

        app(TenantManager::class)->forCompany($order->company_id, function () use ($order, $syncService): void {
            $syncService->syncOrderShipping($order);
        });
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('[Pancake Sync] SyncOrderToPancakeJob failed', [
            'order_id' => $this->orderId,
            'error' => $e?->getMessage(),
        ]);
    }
}
