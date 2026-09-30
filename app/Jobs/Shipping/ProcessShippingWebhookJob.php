<?php

namespace App\Jobs\Shipping;

use App\Models\InboundEvent;
use App\Models\ShippingWebhookEvent;
use App\Services\Orders\OrderTracePublisher;
use App\Services\Shipping\Gateways\NetShip\NetShipStatusMapper;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShippingWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $provider,
        public array $payload,
        public ?int $inboundEventId = null,
        public ?int $companyId = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.shipping_webhooks', 'shipping-webhooks'));
    }

    public function handle(ShippingWebhookService $service, TenantManager $tenant): void
    {
        try {
            $webhookEvent = $tenant->forCompany(
                $this->companyId,
                fn () => $service->process($this->provider, $this->payload),
            );
            if ($this->inboundEventId) {
                InboundEvent::query()->find($this->inboundEventId)?->markProcessed();
            }
            if ($webhookEvent instanceof ShippingWebhookEvent) {
                $this->publishApplied($webhookEvent);
            }
        } catch (Throwable $e) {
            Log::error('[Shipping] Lỗi xử lý webhook', [
                'provider' => $this->provider,
                'company_id' => $this->companyId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function publishApplied(ShippingWebhookEvent $event): void
    {
        $label = $this->provider === 'netship'
            ? NetShipStatusMapper::fromStatusId($event->raw_status)['label']
            : (string) ($event->mapped_status ?? $event->raw_status);

        app(OrderTracePublisher::class)->publish([
            'stage' => 'webhook_applied',
            'source' => $this->provider,
            'company_id' => $this->companyId,
            'order_id' => $event->order_id,
            'order_code' => $event->partner_order_code,
            'partner_order_code' => $event->partner_order_code,
            'external_code' => $event->tracking_number,
            'gateway_order_id' => is_scalar($this->payload['id'] ?? null) ? (string) $this->payload['id'] : null,
            'status_code' => $event->raw_status !== null ? (string) $event->raw_status : $event->mapped_status,
            'summary' => trim($label.' · '.($event->result ?? '')),
            'payload' => [
                'result' => $event->result,
                'mapped_status' => $event->mapped_status,
                'status_label' => $label,
                'cod' => $event->partner_cod,
                'fee' => $event->shipping_fee,
                'note' => $event->note,
            ],
            'dedupe_key' => 'wh-ok:'.$event->id,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        if ($this->inboundEventId) {
            InboundEvent::query()->find($this->inboundEventId)?->markFailed($e?->getMessage() ?? 'Job failed');
        }
    }
}
