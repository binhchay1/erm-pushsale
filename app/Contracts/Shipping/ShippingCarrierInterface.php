<?php

namespace App\Contracts\Shipping;

use App\Models\Order;
use App\Models\Shipment;

interface ShippingCarrierInterface
{
    public function provider(): string;

    public function label(): string;

    public function isReady(): bool;

    public function createFromOrder(Order $order): Shipment;

    public function syncStatus(Order $order, ?Shipment $shipment = null): Shipment;

    /** @return array<string, mixed> */
    public function calculateFee(Order $order): array;

    public function cancel(Order $order, ?Shipment $shipment = null): Shipment;

    /** @return array{success: bool, binary?: string, content_type?: string, message?: string} */
    public function printLabel(Order $order, ?Shipment $shipment = null): array;

    /**
     * Các action kiểm thử API (key => label).
     *
     * @return array<string, string>
     */
    public function testActions(): array;

    /** @return array<string, mixed> */
    public function runTest(string $action): array;
}
