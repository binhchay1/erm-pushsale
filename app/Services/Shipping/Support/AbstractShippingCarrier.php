<?php

namespace App\Services\Shipping\Support;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingPartnerConnection;
use RuntimeException;

abstract class AbstractShippingCarrier implements ShippingCarrierInterface
{
    protected function pendingShipment(Order $order): Shipment
    {
        $existing = Shipment::query()
            ->where('order_id', $order->id)
            ->where('provider', $this->provider())
            ->whereIn('state', [Shipment::STATE_SUBMITTED, Shipment::STATE_PENDING])
            ->latest('id')
            ->first();

        if ($existing?->tracking_number) {
            return $existing;
        }

        return Shipment::query()->updateOrCreate(
            ['order_id' => $order->id, 'provider' => $this->provider()],
            [
                'partner_order_id' => $order->order_code,
                'state' => Shipment::STATE_PENDING,
            ],
        );
    }

    protected function requireShipment(Order $order): Shipment
    {
        $shipment = Shipment::query()
            ->where('order_id', $order->id)
            ->where('provider', $this->provider())
            ->latest('id')
            ->first();

        if (! $shipment) {
            throw new RuntimeException("Đơn chưa có vận đơn {$this->label()}.");
        }

        return $shipment;
    }

    protected function markFailed(Shipment $shipment, string $message, ?array $response = null): never
    {
        $shipment->update([
            'state' => Shipment::STATE_FAILED,
            'error_message' => $message,
            'response_payload' => $response,
        ]);

        throw new RuntimeException($message);
    }

    /**
     * @param  array<string, mixed>  $shipmentData  Cột của bảng shipments
     */
    protected function applySuccess(
        Shipment $shipment,
        Order $order,
        array $shipmentData,
        ?DeliveryStatus $deliveryStatus = null,
    ): Shipment {
        $shipment->update(array_merge($shipmentData, [
            'state' => Shipment::STATE_SUBMITTED,
            'submitted_at' => now(),
            'last_synced_at' => now(),
            'error_message' => null,
        ]));

        $order->update(array_filter([
            'shipping_provider' => $this->provider(),
            'carrier_name' => $this->label(),
            'tracking_number' => $shipmentData['tracking_number'] ?? $order->tracking_number,
            'carrier_service_fee' => $shipmentData['fee'] ?? $order->carrier_service_fee,
            'delivery_status' => ($deliveryStatus ?? DeliveryStatus::PickingUp)->value,
        ], fn ($v) => $v !== null && $v !== ''));

        ShippingPartnerConnection::forProvider($this->provider())->update(['last_synced_at' => now()]);

        return $shipment->fresh();
    }

    protected function markCancelled(Shipment $shipment, Order $order, string $statusText = 'Đã hủy'): Shipment
    {
        $shipment->update([
            'state' => Shipment::STATE_CANCELLED,
            'cancelled_at' => now(),
            'status_text' => $statusText,
            'last_synced_at' => now(),
        ]);

        $order->update(['delivery_status' => DeliveryStatus::CancelWaybill->value]);

        return $shipment->fresh();
    }
}
