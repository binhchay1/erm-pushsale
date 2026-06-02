<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Models\Order;
use App\Models\Shipment;
use RuntimeException;

class CreateShipmentService
{
    public function __construct(private readonly CarrierRegistry $registry) {}

    public function createForOrder(Order $order, ?string $provider = null): Shipment
    {
        $order->loadMissing(['items', 'warehouse']);

        $carrier = $this->registry->resolveForOrder($provider ?? $order->shipping_provider);

        if (! $carrier) {
            throw new RuntimeException('Chưa có đơn vị vận chuyển nào được bật và cấu hình.');
        }

        return $carrier->createFromOrder($order);
    }

    public function sync(Order $order, ?string $provider = null): Shipment
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->syncStatus($order);
    }

    /** @return array<string, mixed> */
    public function calculateFee(Order $order, ?string $provider = null): array
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->calculateFee($order->loadMissing(['items', 'warehouse']));
    }

    public function cancel(Order $order, ?string $provider = null): Shipment
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->cancel($order);
    }

    /** @return array{success: bool, binary?: string, content_type?: string, message?: string} */
    public function printLabel(Order $order, ?string $provider = null): array
    {
        $carrier = $this->carrierForOrder($order, $provider);

        return $carrier->printLabel($order);
    }

    /** @return array<string, mixed> */
    public function runTest(string $provider, string $action): array
    {
        return $this->registry->get($provider)->runTest($action);
    }

    private function carrierForOrder(Order $order, ?string $provider = null): ShippingCarrierInterface
    {
        $key = $provider ?? $order->shipping_provider ?? $order->shipments()->latest('id')->value('provider');

        if (! $key || ! $this->registry->has($key)) {
            throw new RuntimeException('Không xác định được đơn vị vận chuyển của đơn.');
        }

        return $this->registry->get($key);
    }
}
