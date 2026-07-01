<?php

namespace App\Services\Shipping;

use App\Enums\DeliveryStatus;
use App\Enums\ReconciliationStatus;
use App\Models\CarrierSettlementBatch;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Services\Shipping\Settlement\CarrierSettlementSyncService;
use App\Services\Shipping\Settlement\ShipmentReconciliationEngine;
use App\Services\Shipping\Carriers\Ghtk\GhtkStatusMapper;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShippingWebhookService
{
    public function __construct(
        private readonly CarrierSettlementSyncService $settlementSync,
        private readonly ShipmentReconciliationEngine $reconciliationEngine,
    ) {}

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
        $codMismatch = $this->reconciliationEngine->codMismatch($systemCod, $partnerCod);

        if ($order) {
            $order->update(array_filter([
                'carrier_name' => config("shipping_partners.providers.{$provider}.label", Str::title($provider)),
                'tracking_number' => $order->tracking_number ?: $trackingNumber,
                'delivery_status' => $mappedStatus?->value ?? $order->delivery_status,
                'reconciliation_status' => $codMismatch
                    ? ReconciliationStatus::Mismatch->value
                    : $order->reconciliation_status,
            ], fn ($v) => $v !== null && $v !== ''));

            if ($mappedStatus === DeliveryStatus::Paid && $partnerCod !== null && $partnerCod > 0) {
                $this->recordWebhookSettlement($provider, $order, $trackingNumber, $partnerOrderCode, $partnerCod, $payload);
            }

            if (! $codMismatch) {
                $this->reconciliationEngine->reconcileOrder($order->fresh(['settlementLines']));
            }
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

    /** @param  array<string, mixed>  $payload */
    private function recordWebhookSettlement(
        string $provider,
        Order $order,
        ?string $trackingNumber,
        ?string $partnerOrderCode,
        int $partnerCod,
        array $payload,
    ): void {
        $code = 'webhook-'.now()->format('Y-m-d');
        $batch = CarrierSettlementBatch::query()->firstOrCreate(
            ['provider' => $provider, 'settlement_code' => $code],
            ['source' => CarrierSettlementBatch::SOURCE_WEBHOOK, 'imported_at' => now()],
        );

        $this->settlementSync->upsertLine($batch, $provider, $code, [
            'tracking_number' => $trackingNumber ?? $order->tracking_number,
            'partner_order_code' => $partnerOrderCode ?? $order->order_code,
            'cod_amount' => $partnerCod,
            'transaction_code' => 'webhook-'.$order->id.'-'.now()->timestamp,
            'settled_at' => now()->toDateTimeString(),
            'raw_payload' => $payload,
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
