<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Inventory\InventoryDeductionService;
use RuntimeException;

class CreateShipmentService
{
    public function __construct(
        private readonly CarrierRegistry $registry,
        private readonly InventoryDeductionService $inventory,
        private readonly ShippingFeePresenter $feePresenter,
    ) {}

    public function createForOrder(Order $order, ?string $provider = null): Shipment
    {
        $order->loadMissing(['items', 'warehouse']);

        if (! $order->inventory_deducted_at && ! $this->inventory->hasSufficientStock($order)) {
            throw new RuntimeException(__('messages.shipping_actions.out_of_stock_create'));
        }

        $carrier = $this->registry->resolveForOrder($provider ?? $order->shipping_provider);

        if (! $carrier) {
            throw new RuntimeException(__('messages.shipping_actions.no_carrier_configured'));
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
        $raw = $carrier->calculateFee($order->loadMissing(['items', 'warehouse']));

        return array_merge($raw, [
            'display' => $this->feePresenter->present($raw),
        ]);
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
            throw new RuntimeException(__('messages.shipping_actions.carrier_undetermined'));
        }

        return $this->registry->get($key);
    }
}
