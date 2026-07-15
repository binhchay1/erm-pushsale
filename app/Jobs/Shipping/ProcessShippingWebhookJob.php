<?php

namespace App\Jobs\Shipping;

use App\Models\InboundEvent;
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
            $tenant->forCompany($this->companyId, fn () => $service->process($this->provider, $this->payload));
            if ($this->inboundEventId) {
                InboundEvent::query()->withoutTenant()->find($this->inboundEventId)?->markProcessed();
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

    public function failed(?Throwable $e): void
    {
        if ($this->inboundEventId) {
            InboundEvent::query()->withoutTenant()->find($this->inboundEventId)?->markFailed($e?->getMessage() ?? 'Job failed');
        }
    }
}
