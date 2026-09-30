<?php

namespace App\Jobs\Orders;

use App\Models\Order;
use App\Models\OrderTrace;
use App\Models\ShippingGatewayTrace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Ghi một mốc hành trình. Chạy trên queue riêng để webhook và tạo đơn không chờ insert.
 */
class RecordOrderTraceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 20;

    /** @param array<string, mixed> $event */
    public function __construct(public array $event)
    {
        $this->onQueue((string) config('saleops.queues.order_traces', 'order-traces'));
    }

    public function handle(): void
    {
        if (($this->event['stage'] ?? '') === 'shipment_link') {
            $this->linkShipment();

            return;
        }

        $this->store();
    }

    private function store(): void
    {
        $companyId = $this->event['company_id'] ?? null;
        if (! $companyId && ! empty($this->event['order_id'])) {
            $companyId = Order::query()->whereKey($this->event['order_id'])->value('company_id');
        }

        $row = [
            'company_id' => $companyId,
            'order_id' => $this->event['order_id'] ?? null,
            'shipment_id' => $this->event['shipment_id'] ?? null,
            'stage' => (string) $this->event['stage'],
            'source' => (string) ($this->event['source'] ?? 'system'),
            'action' => $this->event['action'] ?? null,
            'order_code' => $this->limit($this->event['order_code'] ?? null, 64),
            'phone' => $this->limit($this->event['phone'] ?? null, 20),
            'external_code' => $this->limit($this->event['external_code'] ?? null, 64),
            'partner_order_code' => $this->limit($this->event['partner_order_code'] ?? null, 64),
            'gateway_order_id' => $this->limit($this->event['gateway_order_id'] ?? null, 64),
            'lookup_code' => $this->limit($this->event['lookup_code'] ?? null, 64),
            'status_code' => $this->limit($this->event['status_code'] ?? null, 40),
            'summary' => $this->limit($this->event['summary'] ?? null, 255),
            'payload' => $this->event['payload'] ?? null,
            'dedupe_key' => (string) $this->event['dedupe_key'],
            'occurred_at' => Carbon::parse($this->event['occurred_at'] ?? now()),
        ];

        try {
            OrderTrace::query()->create($row);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if (! ($this->event['gateway_log'] ?? false)) {
            return;
        }

        ShippingGatewayTrace::query()->create([
            'company_id' => $companyId,
            'order_id' => $row['order_id'],
            'shipment_id' => $row['shipment_id'],
            'gateway' => 'netship',
            'action' => (string) ($row['action'] ?: 'request'),
            'external_code' => $row['external_code'],
            'partner_order_code' => $row['partner_order_code'],
            'gateway_order_id' => $row['gateway_order_id'],
            'lookup_code' => $row['lookup_code'],
            'http_status' => $this->event['http_status'] ?? null,
            'success' => (bool) ($this->event['success'] ?? false),
            'request_payload' => null,
            'response_payload' => $row['payload'],
        ]);
    }

    private function linkShipment(): void
    {
        $orderId = (int) ($this->event['order_id'] ?? 0);
        $shipmentId = (int) ($this->event['shipment_id'] ?? 0);
        if ($orderId === 0 || $shipmentId === 0) {
            return;
        }

        $external = $this->limit($this->event['external_code'] ?? null, 64);
        $traceId = OrderTrace::query()
            ->where('order_id', $orderId)
            ->where('action', 'create_order')
            ->latest('id')
            ->value('id');
        $gatewayId = ShippingGatewayTrace::query()
            ->where('order_id', $orderId)
            ->where('action', 'create_order')
            ->latest('id')
            ->value('id');

        if (! $traceId && ! $gatewayId) {
            if ($this->attempts() < $this->tries) {
                $this->release(2);
            }

            return;
        }

        $update = ['shipment_id' => $shipmentId];
        if ($external !== null) {
            $update['external_code'] = $external;
        }
        if ($traceId) {
            OrderTrace::query()->whereKey($traceId)->update($update);
        }
        if ($gatewayId) {
            ShippingGatewayTrace::query()->whereKey($gatewayId)->update($update);
        }
    }

    private function limit(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
