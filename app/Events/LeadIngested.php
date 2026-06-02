<?php

namespace App\Events;

use App\Models\LeadIngestion;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadIngested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LeadIngestion $ingestion,
        public ?Order $order = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dashboard.admin'),
            new PrivateChannel('dashboard.sales'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lead.ingested';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'ingestion_id' => $this->ingestion->id,
            'platform' => $this->ingestion->platform,
            'customer_phone' => $this->ingestion->customer_phone,
            'order_id' => $this->order?->id,
            'status' => $this->ingestion->status->value,
        ];
    }
}
