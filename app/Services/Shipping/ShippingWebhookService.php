<?php

namespace App\Services\Shipping;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Services\Shipping\Carriers\Ghtk\GhtkStatusMapper;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShippingWebhookService
{
    /** @param  array<string, mixed>  $payload */
    public function process(string $provider, array $payload): ShippingWebhookEvent
    {
        $trackingNumber = $this->firstFilled($payload, [
            'tracking_number', 'trackingNo', 'tracking_no', 'billcode', 'label_id', 'label',
        ]);
        $partnerOrderCode = $this->firstFilled($payload, [
            'partner_order_code', 'partnerOrderCode', 'order_code', 'orderCode',
            'client_order_code', 'reference', 'order_id', 'partner_id',
        ]);
        $rawStatus = $this->firstFilled($payload, [
            'status', 'status_text', 'state', 'current_status', 'order_status', 'status_name',
        ]);
        $statusId = $this->toInt($this->firstFilled($payload, ['status_id']));
        $mappedStatus = $this->mapStatus($rawStatus, $statusId, $provider);
        $partnerCod = $this->toInt($this->firstFilled($payload, [
            'cod', 'cod_amount', 'money_collect', 'amount_to_collect', 'pick_money',
        ]));
        $eventType = $this->firstFilled($payload, ['event', 'event_type', 'type']) ?? 'status_update';

        $order = $this->resolveOrder($trackingNumber, $partnerOrderCode);
        $systemCod = $order?->amount_to_collect ? (int) $order->amount_to_collect : null;
        $codMismatch = $order && $partnerCod !== null && $systemCod !== null
            ? abs($partnerCod - $systemCod) > 500
            : false;

        if ($order) {
            $order->update(array_filter([
                'carrier_name' => config("shipping_partners.providers.{$provider}.label", Str::title($provider)),
                'tracking_number' => $order->tracking_number ?: $trackingNumber,
                'delivery_status' => $mappedStatus?->value ?? $order->delivery_status,
                'reconciliation_status' => $codMismatch
                    ? 'mismatch'
                    : ($mappedStatus === DeliveryStatus::Delivered ? 'reconciled' : $order->reconciliation_status),
            ], fn ($v) => $v !== null && $v !== ''));
        }

        return ShippingWebhookEvent::query()->create([
            'provider' => $provider,
            'event_type' => $eventType,
            'partner_order_code' => $partnerOrderCode,
            'tracking_number' => $trackingNumber,
            'raw_status' => $rawStatus,
            'mapped_status' => $mappedStatus?->value,
            'partner_cod' => $partnerCod,
            'system_cod' => $systemCod,
            'is_cod_mismatch' => $codMismatch,
            'order_id' => $order?->id,
            'payload' => $payload,
            'received_at' => now(),
            'result' => $order ? 'matched' : 'unmatched',
            'note' => $order ? null : 'Không tìm thấy đơn theo tracking/order_code',
        ]);
    }

    protected function resolveOrder(?string $trackingNumber, ?string $partnerOrderCode): ?Order
    {
        if ($trackingNumber) {
            $order = Order::query()->where('tracking_number', $trackingNumber)->first();
            if ($order) {
                return $order;
            }
        }

        if ($partnerOrderCode) {
            return Order::query()->where('order_code', $partnerOrderCode)->first();
        }

        return null;
    }

    protected function mapStatus(?string $raw, ?int $statusId, string $provider): ?DeliveryStatus
    {
        if ($provider === 'ghtk' && $statusId) {
            return GhtkStatusMapper::fromStatusId($statusId, $raw)['status'];
        }

        return DeliveryStatusTextMapper::map($raw);
    }

    /** @param  array<string, mixed>  $payload */
    protected function firstFilled(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (filled($value) && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function toInt(?string $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/[^\d-]/', '', $value);

        return $digits === '' ? null : (int) $digits;
    }
}
