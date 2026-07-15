<?php

namespace App\Listeners;

use App\Events\OrderClosed;
use App\Jobs\CreateShipmentJob;
use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\CarrierRegistry;

class DispatchShipmentOnOrderClosed
{
    public function __construct(private readonly CarrierRegistry $registry) {}

    public function handle(OrderClosed $event): void
    {
        $order = $event->order->loadMissing('company');
        $provider = $order->shipping_provider ?: $order->company?->default_shipping_provider;

        if (! $provider || ! $this->registry->has($provider)) {
            return;
        }

        $connection = ShippingPartnerConnection::forProvider($provider);
        $settings = array_merge(
            config('shipping_partners.default_settings', []),
            $connection->settings ?? [],
        );

        if (! $connection->is_enabled
            || ! (bool) ($settings['auto_create_waybill'] ?? false)
            || ! $this->registry->get($provider)->isReady()) {
            return;
        }

        if (! $order->shipping_provider) {
            $order->update([
                'shipping_provider' => $provider,
                'shipping_method' => $order->shipping_method
                    ?: $order->company?->default_shipping_method,
            ]);
        }

        if (config('queue.default') === 'sync') {
            CreateShipmentJob::dispatchSync($order->id, $provider);
        } else {
            CreateShipmentJob::dispatch($order->id, $provider)->afterCommit();
        }
    }
}
