<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'redis';

    public function __construct(public UserNotification $notification) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->notification->user_id),
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('saleops.queues.notification_broadcasts', 'broadcasts-notifications');
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->notification->toFrontendArray();
    }
}
