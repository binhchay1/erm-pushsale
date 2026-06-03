<?php

namespace App\Services\DataDeletion;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingApiLog;
use App\Models\ShippingWebhookEvent;
use Illuminate\Support\Facades\DB;

class OrderDeletionService
{
    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $shipmentIds = $order->shipments()->pluck('id');

            if ($shipmentIds->isNotEmpty()) {
                ShippingApiLog::query()->whereIn('shipment_id', $shipmentIds)->delete();
            }

            ShippingApiLog::query()->where('order_id', $order->id)->delete();
            ShippingWebhookEvent::query()->where('order_id', $order->id)->delete();
            Shipment::query()->where('order_id', $order->id)->delete();
            $order->items()->delete();
            $order->delete();
        });
    }
}
