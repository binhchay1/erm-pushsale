<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ping a specific telesales rep that their workspace data changed (new lead
 * assigned to them). Broadcast on the shared sales channel; the client filters
 * by `sale_user_id` so we avoid touching the per-user notification channel.
 * Queued (ShouldBroadcast) so a broadcaster outage never blocks the request.
 */
class SaleWorkspaceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $saleUserId) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dashboard.sales'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'sale_user_id' => $this->saleUserId,
            'at' => now()->toIso8601String(),
        ];
    }
}
