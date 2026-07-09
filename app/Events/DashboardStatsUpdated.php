<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'redis';

    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        public string $channelRole,
        public array $stats,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dashboard.'.$this->channelRole),
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('saleops.queues.dashboard_broadcasts', 'broadcasts-dashboard');
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stats' => $this->stats,
        ];
    }
}
