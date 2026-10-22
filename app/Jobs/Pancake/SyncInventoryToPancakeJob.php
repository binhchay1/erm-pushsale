<?php

namespace App\Jobs\Pancake;

use App\Models\Scopes\TenantScope;
use App\Models\WarehouseInventoryMovement;
use App\Services\Pancake\PancakeOutboundSyncService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncInventoryToPancakeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $movementId,
    ) {
        $this->onQueue(config('saleops.queues.pancake_orders', 'pancake-orders'));
    }

    public function handle(PancakeOutboundSyncService $syncService): void
    {
        $movement = WarehouseInventoryMovement::query()
            ->withoutGlobalScope(TenantScope::class)
            ->find($this->movementId);

        if (! $movement) {
            return;
        }

        app(TenantManager::class)->forCompany($movement->company_id, function () use ($movement, $syncService): void {
            $syncService->syncInventoryMovement($movement);
        });
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('[Pancake Sync] SyncInventoryToPancakeJob failed', [
            'movement_id' => $this->movementId,
            'error' => $e?->getMessage(),
        ]);
    }
}
