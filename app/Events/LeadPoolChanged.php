<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight ping: the unallocated lead pool changed (new lead / allocation).
 * Admin & allocator lead tables reload their `leads` prop on receipt.
 * Queued (ShouldBroadcast) so a broadcaster outage never blocks the request.
 */
class LeadPoolChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'redis';

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dashboard.admin'),
            new PrivateChannel('dashboard.allocator'),
            new PrivateChannel('dashboard.marketing'),
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('saleops.queues.dashboard_broadcasts', 'broadcasts-dashboard');
    }

    public function broadcastAs(): string
    {
        return 'leads.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['at' => now()->toIso8601String()];
    }
}
