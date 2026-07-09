<?php

namespace App\Events\CustomerInteractions;

use App\Models\PancakeCustomerMessage;
use App\Services\CustomerInteractions\CustomerConversationChannel;
use App\Services\Pancake\PancakeCustomerChatService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PancakeCustomerMessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'redis';

    public function __construct(public PancakeCustomerMessage $message) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(CustomerConversationChannel::pancakeForConversation(
                (int) $this->message->company_id,
                (string) $this->message->conversation_id,
            )),
        ];
    }

    public function broadcastAs(): string
    {
        return 'customer.pancake-message.created';
    }

    public function broadcastQueue(): string
    {
        return (string) config('saleops.queues.pancake_chat_broadcasts', 'broadcasts-pancake-chat');
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message' => app(PancakeCustomerChatService::class)->present($this->message),
            'conversation' => [
                'type' => 'pancake',
                'companyId' => $this->message->company_id,
                'pageId' => $this->message->page_id,
                'conversationId' => $this->message->conversation_id,
            ],
        ];
    }
}
