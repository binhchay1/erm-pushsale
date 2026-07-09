<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Shipping\CreateShipmentService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateShipmentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $orderId,
        public ?string $provider = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.shipments', 'shipments'));
    }

    public function handle(CreateShipmentService $service): void
    {
        $order = Order::query()->with(['items', 'warehouse'])->find($this->orderId);

        if (! $order || ! $order->closed_at) {
            return;
        }

        try {
            app(TenantManager::class)->forCompany($order->company_id, fn () => $service->createForOrder($order, $this->provider));
        } catch (\Throwable $e) {
            Log::warning('[Shipping] Không tạo được vận đơn', [
                'order_id' => $order->id,
                'provider' => $this->provider ?? $order->shipping_provider,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
