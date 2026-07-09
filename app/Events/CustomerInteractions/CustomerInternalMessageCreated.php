<?php

namespace App\Events\CustomerInteractions;

use App\Models\CustomerInternalMessage;
use App\Services\CustomerInteractions\CustomerConversationChannel;
use App\Services\CustomerInteractions\CustomerInternalMessagePresenter;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerInternalMessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CustomerInternalMessage $message) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(CustomerConversationChannel::internalForPhone(
                (int) $this->message->company_id,
                (string) $this->message->customer_phone,
            )),
        ];
    }

    public function broadcastAs(): string
    {
        return 'customer.internal-message.created';
    }

    public function broadcastQueue(): string
    {
        return (string) config('saleops.queues.internal_chat_broadcasts', 'broadcasts-internal-chat');
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $this->message->loadMissing(['author:id,name,role,org_level', 'order:id,order_code']);

        return [
            'message' => CustomerInternalMessagePresenter::toArray($this->message),
            'conversation' => [
                'type' => 'internal',
                'companyId' => $this->message->company_id,
            ],
        ];
    }
}
