<?php

namespace App\Listeners;

use App\Events\OrderClosed;
use App\Jobs\CreateShipmentJob;
use App\Services\Shipping\CarrierRegistry;

class DispatchShipmentOnOrderClosed
{
    public function __construct(private readonly CarrierRegistry $registry) {}

    public function handle(OrderClosed $event): void
    {
        if ($this->registry->readyProviders() === []) {
            return;
        }

        $provider = $event->order->shipping_provider;

        if (config('queue.default') === 'sync') {
            CreateShipmentJob::dispatchSync($event->order->id, $provider);
        } else {
            CreateShipmentJob::dispatch($event->order->id, $provider)->afterCommit();
        }
    }
}
