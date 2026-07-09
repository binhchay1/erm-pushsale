<?php

namespace App\Jobs\Pancake;

use App\Events\CustomerInteractions\PancakeCustomerMessageCreated;
use App\Models\IntegrationConnection;
use App\Models\Scopes\TenantScope;
use App\Services\Pancake\PancakeCustomerChatService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPancakeMessageWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $connectionId,
        public array $payload,
        public ?string $correlationId = null,
    ) {
        $this->onQueue(config('saleops.queues.pancake_chat_sync', 'pancake-chat'));
    }

    public function handle(PancakeCustomerChatService $chat): void
    {
        $connection = IntegrationConnection::query()
            ->withoutGlobalScope(TenantScope::class)
            ->find($this->connectionId);

        if (! $connection) {
            return;
        }

        app(TenantManager::class)->forCompany($connection->company_id, function () use ($chat, $connection): void {
            $messages = $chat->cacheWebhookPayload($connection, $this->payload);

            foreach ($messages as $message) {
                try {
                    event(new PancakeCustomerMessageCreated($message));
                } catch (\Throwable $e) {
                    Log::warning('Pancake customer chat broadcast failed', [
                        'message_id' => $message->id,
                        'correlation_id' => $this->correlationId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
