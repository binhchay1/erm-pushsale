<?php

namespace App\Jobs\Shipping;

use App\Services\Shipping\ShippingWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessShippingWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public array $payload,
    ) {}

    public function handle(ShippingWebhookService $service): void
    {
        try {
            $service->process($this->provider, $this->payload);
        } catch (\Throwable $e) {
            Log::error('[Shipping] Lỗi xử lý webhook', [
                'provider' => $this->provider,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
