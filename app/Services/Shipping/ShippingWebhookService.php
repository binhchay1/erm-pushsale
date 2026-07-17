<?php

namespace App\Services\Shipping;

use App\Enums\DeliveryStatus;
use App\Enums\ReconciliationStatus;
use App\Models\CarrierSettlementBatch;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingPartnerConnection;
use App\Models\ShippingStatusEvent;
use App\Models\ShippingWebhookEvent;
use App\Models\WarehouseReturnReceipt;
use App\Services\Inventory\InventoryReturnService;
use App\Services\Shipping\Carriers\Ghtk\GhtkStatusMapper;
use App\Services\Shipping\Settlement\CarrierSettlementSyncService;
use App\Services\Shipping\Settlement\ShipmentReconciliationEngine;
use App\Services\Shipping\Support\DeliveryStatusTextMapper;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShippingWebhookService
{
    public function __construct(
        private readonly CarrierSettlementSyncService $settlementSync,
        private readonly ShipmentReconciliationEngine $reconciliationEngine,
        private readonly InventoryReturnService $inventoryReturn,
    ) {}

    /** @param array<string, mixed> $payload */
    public function process(string $provider, array $payload): ShippingWebhookEvent
    {
        $flat = $this->normalizeRoot($payload);
        $trackingNumber = $this->firstFilled($flat, [
            'tracking_number', 'trackingNo', 'tracking_no', 'trackingCode', 'waybill',
            'billcode', 'label_id', 'label', 'data.tracking_number', 'order.tracking_number',
        ]);
        $partnerOrderCode = $this->firstFilled($flat, [
            'partner_order_code', 'partnerOrderCode', 'order_code', 'orderCode', 'client_order_code',
            'reference', 'partner_id', 'data.order_code', 'order.order_code', 'merchant_order_id',
        ]);
        $rawStatus = $this->firstFilled($flat, [
            'status_text', 'status_name', 'status', 'state', 'current_status', 'order_status',
            'data.status', 'order.status', 'shipment.status',
        ]);
        $statusId = $this->toInt($this->firstFilled($flat, ['status_id', 'statusId', 'data.status_id']));
        $mappedStatus = $this->mapStatus($rawStatus, $statusId, $provider);
        $eventType = $this->firstFilled($flat, ['event', 'event_type', 'type', 'eventName']) ?: 'status_update';
        $occurredAt = $this->parseDate($this->firstFilled($flat, [
            'occurred_at', 'event_time', 'updated_at', 'status_time', 'time', 'timestamp', 'data.updated_at',
        ])) ?: now();

        $financials = [
            'cod_amount' => $this->money($this->firstValue($flat, ['cod_amount', 'cod', 'money_collect', 'amount_to_collect', 'pick_money', 'data.cod_amount'])),
            'cod_collected' => $this->money($this->firstValue($flat, ['cod_collected', 'collected_cod', 'collected_amount', 'data.cod_collected'])),
            'cod_remitted' => $this->money($this->firstValue($flat, ['cod_remitted', 'remitted_cod', 'paid_cod', 'transfer_amount', 'data.cod_remitted'])),
            'shipping_fee' => $this->money($this->firstValue($flat, ['shipping_fee', 'delivery_fee', 'service_fee', 'total_fee', 'fee', 'data.shipping_fee'])),
            'return_fee' => $this->money($this->firstValue($flat, ['return_fee', 'return_shipping_fee', 'fee_return', 'data.return_fee'])),
            'cod_fee' => $this->money($this->firstValue($flat, ['cod_fee', 'collection_fee', 'fee_cod', 'data.cod_fee'])),
            'insurance_fee' => $this->money($this->firstValue($flat, ['insurance_fee', 'fee_insurance', 'data.insurance_fee'])),
            'other_fee' => $this->money($this->firstValue($flat, ['other_fee', 'extra_fee', 'additional_fee', 'data.other_fee'])),
            'compensation_amount' => $this->money($this->firstValue($flat, ['compensation_amount', 'compensation', 'refund_amount', 'data.compensation_amount'])),
        ];

        $order = $this->resolveOrder($trackingNumber, $partnerOrderCode);
        $shipment = $this->resolveShipment($order, $provider, $trackingNumber, $partnerOrderCode);
        $systemCod = $order?->amount_to_collect !== null ? (int) $order->amount_to_collect : null;
        $partnerCod = $financials['cod_amount'] ?: ($financials['cod_collected'] ?: $financials['cod_remitted']);
        $codMismatch = $this->reconciliationEngine->codMismatch($systemCod, $partnerCod ?: null);
        $eventHash = $this->eventHash($provider, $payload, $trackingNumber, $partnerOrderCode, $eventType, $rawStatus);

        $existing = ShippingWebhookEvent::query()->where('provider', $provider)->where('event_hash', $eventHash)->first();
        if ($existing) {
            return $existing;
        }

        if ($order) {
            $shipment ??= Shipment::query()->firstOrCreate(
                ['order_id' => $order->id, 'provider' => $provider],
                ['partner_order_id' => $partnerOrderCode ?: $order->order_code, 'tracking_number' => $trackingNumber, 'state' => Shipment::STATE_SUBMITTED],
            );
            $this->applyShipment($shipment, $financials, $trackingNumber, $rawStatus, $mappedStatus, $occurredAt, $payload);
            $this->applyOrder($order, $provider, $shipment, $financials, $mappedStatus, $codMismatch, $occurredAt);
            $this->recordStatusEvent($provider, $eventHash, $order, $shipment, $rawStatus, $mappedStatus, $occurredAt, $financials, $flat, $payload);

            if ($mappedStatus === DeliveryStatus::Returned) {
                $settings = array_merge(config('shipping_partners.default_settings', []), ShippingPartnerConnection::forProvider($provider)->settings ?? []);
                if ((bool) ($settings['auto_restock_return'] ?? true)) {
                    $this->inventoryReturn->receiveReturn(
                        $order->fresh(['items']),
                        $this->firstFilled($flat, ['return_reason', 'reason', 'note', 'status_note']),
                        null,
                        $this->returnLines($flat),
                        WarehouseReturnReceipt::SOURCE_WEBHOOK,
                        $shipment,
                        'Tự động nhập hoàn từ webhook '.$provider,
                    );
                }
            }

            if (($mappedStatus === DeliveryStatus::Paid || $financials['cod_remitted'] > 0) && $partnerCod > 0) {
                $this->recordWebhookSettlement($provider, $order, $shipment, $partnerOrderCode, $financials, $payload, $occurredAt);
            }

            $this->reconciliationEngine->reconcileOrder($order->fresh(['settlementLines']));
        }

        return ShippingWebhookEvent::query()->create([
            'provider' => $provider,
            'event_hash' => $eventHash,
            'event_type' => $eventType,
            'partner_order_code' => $partnerOrderCode,
            'tracking_number' => $trackingNumber,
            'raw_status' => $rawStatus,
            'mapped_status' => $mappedStatus?->value,
            'partner_cod' => $partnerCod ?: null,
            'system_cod' => $systemCod,
            'shipping_fee' => $financials['shipping_fee'],
            'return_fee' => $financials['return_fee'],
            'cod_fee' => $financials['cod_fee'],
            'other_fee' => $financials['other_fee'] + $financials['insurance_fee'],
            'compensation_amount' => $financials['compensation_amount'],
            'is_cod_mismatch' => $codMismatch,
            'order_id' => $order?->id,
            'payload' => $payload,
            'normalized_payload' => array_merge($financials, [
                'tracking_number' => $trackingNumber,
                'partner_order_code' => $partnerOrderCode,
                'mapped_status' => $mappedStatus?->value,
            ]),
            'received_at' => now(),
            'occurred_at' => $occurredAt,
            'result' => $order ? 'matched' : 'unmatched',
            'note' => $order ? null : 'Không tìm thấy đơn theo tracking/order_code',
        ]);
    }

    /** @param array<string,int> $financials @param array<string,mixed> $payload */
    private function applyShipment(Shipment $shipment, array $financials, ?string $tracking, ?string $rawStatus, ?DeliveryStatus $mapped, Carbon $at, array $payload): void
    {
        $timestamps = ['last_event_at' => $at, 'last_synced_at' => now()];
        if ($mapped === DeliveryStatus::Posted) $timestamps['posted_at'] = $shipment->posted_at ?: $at;
        if ($mapped === DeliveryStatus::PickingUp) $timestamps['picked_up_at'] = $shipment->picked_up_at ?: $at;
        if (in_array($mapped, [DeliveryStatus::Delivered, DeliveryStatus::DeliveryComplete, DeliveryStatus::PartialDelivery], true)) $timestamps['delivered_at'] = $shipment->delivered_at ?: $at;
        if ($mapped === DeliveryStatus::Returning) $timestamps['returning_at'] = $shipment->returning_at ?: $at;
        if ($mapped === DeliveryStatus::Returned) $timestamps['returned_at'] = $shipment->returned_at ?: $at;
        if ($mapped === DeliveryStatus::Paid || $financials['cod_remitted'] > 0) $timestamps['cod_remitted_at'] = $shipment->cod_remitted_at ?: $at;

        $shipment->update(array_merge($timestamps, [
            'tracking_number' => $shipment->tracking_number ?: $tracking,
            'status_text' => $rawStatus ?: $shipment->status_text,
            'state' => $mapped === DeliveryStatus::CancelWaybill ? Shipment::STATE_CANCELLED : Shipment::STATE_SUBMITTED,
            'fee' => $this->moneyOrCurrent($financials['shipping_fee'], $shipment->fee),
            'insurance_fee' => $this->moneyOrCurrent($financials['insurance_fee'], $shipment->insurance_fee),
            'cod_amount' => $this->moneyOrCurrent($financials['cod_amount'], $shipment->cod_amount),
            'cod_collected' => $this->moneyOrCurrent($financials['cod_collected'], $shipment->cod_collected),
            'cod_remitted' => $this->moneyOrCurrent($financials['cod_remitted'], $shipment->cod_remitted),
            'cod_fee' => $this->moneyOrCurrent($financials['cod_fee'], $shipment->cod_fee),
            'return_fee' => $this->moneyOrCurrent($financials['return_fee'], $shipment->return_fee),
            'other_fee' => $this->moneyOrCurrent($financials['other_fee'], $shipment->other_fee),
            'compensation_amount' => $this->moneyOrCurrent($financials['compensation_amount'], $shipment->compensation_amount),
            'response_payload' => $payload,
        ]));
    }

    /** @param array<string,int> $financials */
    private function applyOrder(Order $order, string $provider, Shipment $shipment, array $financials, ?DeliveryStatus $mapped, bool $codMismatch, Carbon $at): void
    {
        $order->update(array_filter([
            'shipping_provider' => $order->shipping_provider ?: $provider,
            'carrier_name' => config("shipping_partners.providers.{$provider}.label", Str::headline($provider)),
            'tracking_number' => $order->tracking_number ?: $shipment->tracking_number,
            'delivery_status' => $mapped?->value,
            'reconciliation_status' => $codMismatch ? ReconciliationStatus::Mismatch->value : $order->reconciliation_status,
            'carrier_service_fee' => $financials['shipping_fee'] ?: null,
            'carrier_return_fee' => $financials['return_fee'] ?: null,
            'cod_fee' => $financials['cod_fee'] ?: null,
            'carrier_other_fee' => ($financials['other_fee'] + $financials['insurance_fee']) ?: null,
            'carrier_compensation_amount' => $financials['compensation_amount'] ?: null,
            'last_delivery_event_at' => $at,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /** @param array<string,int> $financials @param array<string,mixed> $flat @param array<string,mixed> $payload */
    private function recordStatusEvent(string $provider, string $eventKey, Order $order, Shipment $shipment, ?string $rawStatus, ?DeliveryStatus $mapped, Carbon $at, array $financials, array $flat, array $payload): void
    {
        ShippingStatusEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_key' => $eventKey],
            [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'raw_status' => $rawStatus,
                'mapped_status' => $mapped?->value,
                'location' => $this->firstFilled($flat, ['location', 'current_location', 'address', 'data.location']),
                'note' => $this->firstFilled($flat, ['note', 'status_note', 'message', 'reason']),
                'financials' => $financials,
                'payload' => $payload,
                'occurred_at' => $at,
            ],
        );
    }

    /** @param array<string,int> $financials @param array<string,mixed> $payload */
    private function recordWebhookSettlement(string $provider, Order $order, Shipment $shipment, ?string $partnerOrderCode, array $financials, array $payload, Carbon $at): void
    {
        $code = 'webhook-'.$at->format('Y-m-d');
        $batch = CarrierSettlementBatch::query()->firstOrCreate(
            ['provider' => $provider, 'settlement_code' => $code],
            ['source' => CarrierSettlementBatch::SOURCE_WEBHOOK, 'imported_at' => now()],
        );

        $this->settlementSync->upsertLine($batch, $provider, $code, [
            'tracking_number' => $shipment->tracking_number,
            'partner_order_code' => $partnerOrderCode ?: $order->order_code,
            'cod_amount' => $financials['cod_remitted'] ?: ($financials['cod_collected'] ?: $financials['cod_amount']),
            'carrier_fee' => $financials['shipping_fee'],
            'return_fee' => $financials['return_fee'],
            'cod_fee' => $financials['cod_fee'],
            'insurance_fee' => $financials['insurance_fee'],
            'other_fee' => $financials['other_fee'],
            'compensation_amount' => $financials['compensation_amount'],
            'transaction_code' => 'webhook-'.$shipment->id.'-'.$at->timestamp,
            'settled_at' => $at->toDateTimeString(),
            'raw_payload' => $payload,
        ]);
    }

    protected function resolveOrder(?string $trackingNumber, ?string $partnerOrderCode): ?Order
    {
        if ($trackingNumber) {
            $order = Order::query()->where('tracking_number', $trackingNumber)
                ->orWhereHas('shipments', fn ($query) => $query->where('tracking_number', $trackingNumber))
                ->first();
            if ($order) return $order;
        }
        return $partnerOrderCode ? Order::query()->where('order_code', $partnerOrderCode)->first() : null;
    }

    private function resolveShipment(?Order $order, string $provider, ?string $tracking, ?string $partnerCode): ?Shipment
    {
        if ($tracking) {
            $shipment = Shipment::query()->where('provider', $provider)->where('tracking_number', $tracking)->first();
            if ($shipment) return $shipment;
        }
        if ($order) return $order->shipments()->where('provider', $provider)->latest('id')->first();
        if ($partnerCode) return Shipment::query()->where('provider', $provider)->where('partner_order_id', $partnerCode)->first();
        return null;
    }

    protected function mapStatus(?string $raw, ?int $statusId, string $provider): ?DeliveryStatus
    {
        if ($provider === 'ghtk' && $statusId) return GhtkStatusMapper::fromStatusId($statusId, $raw)['status'];
        return DeliveryStatusTextMapper::map($raw);
    }

    /** @param array<string,mixed> $payload */
    private function normalizeRoot(array $payload): array
    {
        $root = $payload;
        foreach (['data', 'order', 'shipment', 'result'] as $key) {
            if (is_array($payload[$key] ?? null)) $root = array_replace_recursive($root, $payload[$key]);
        }
        return $root;
    }

    /** @param array<string,mixed> $payload */
    protected function firstFilled(array $payload, array $keys): ?string
    {
        $value = $this->firstValue($payload, $keys);
        return filled($value) && is_scalar($value) ? (string) $value : null;
    }

    /** @param array<string,mixed> $payload */
    private function firstValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if ($value !== null && $value !== '' && is_scalar($value)) return $value;
        }
        return null;
    }

    private function toInt(?string $value): ?int
    {
        if (! filled($value)) return null;
        $digits = preg_replace('/[^\d-]/', '', $value);
        return $digits === '' ? null : (int) $digits;
    }

    private function moneyOrCurrent(mixed $incoming, mixed $current): int
    {
        $incoming = (int) $incoming;

        return $incoming > 0 ? $incoming : max(0, (int) ($current ?? 0));
    }

    private function money(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_int($value)) return max(0, $value);
        if (is_float($value)) return max(0, (int) round($value));
        $digits = preg_replace('/[^\d-]/', '', (string) $value);
        return $digits === '' ? 0 : max(0, (int) $digits);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! filled($value)) return null;
        try {
            if (ctype_digit((string) $value)) {
                $timestamp = (int) $value;
                if ($timestamp > 9999999999) $timestamp = (int) floor($timestamp / 1000);
                return Carbon::createFromTimestamp($timestamp);
            }
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $payload */
    private function eventHash(string $provider, array $payload, ?string $tracking, ?string $orderCode, string $type, ?string $status): string
    {
        // Không dùng now() trong khóa idempotency: nhiều hãng gửi lại nguyên payload nhưng không có event_time.
        $explicit = $this->firstFilled($payload, ['event_id', 'id', 'webhook_id', 'transaction_id']);
        $identity = $explicit ?: hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return hash('sha256', implode('|', [$provider, $tracking, $orderCode, $type, $status, $identity]));
    }

    /** @param array<string,mixed> $flat @return list<array<string,mixed>>|null */
    private function returnLines(array $flat): ?array
    {
        $items = Arr::get($flat, 'return_items') ?? Arr::get($flat, 'items_returned') ?? Arr::get($flat, 'returned_items');
        if (! is_array($items)) return null;

        return collect($items)->filter(fn ($item) => is_array($item))->map(fn (array $item) => [
            'order_item_id' => $item['order_item_id'] ?? null,
            'product_id' => $item['product_id'] ?? $item['productId'] ?? null,
            'received_quantity' => $item['received_quantity'] ?? $item['quantity'] ?? 0,
            'restock_quantity' => $item['restock_quantity'] ?? $item['sellable_quantity'] ?? null,
            'damaged_quantity' => $item['damaged_quantity'] ?? 0,
            'missing_quantity' => $item['missing_quantity'] ?? 0,
            'condition' => $item['condition'] ?? 'sellable',
            'note' => $item['note'] ?? null,
        ])->values()->all();
    }
}
