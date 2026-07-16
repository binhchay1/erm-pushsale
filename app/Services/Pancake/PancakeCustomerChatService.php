<?php

namespace App\Services\Pancake;

use App\Models\IntegrationConnection;
use App\Models\Scopes\TenantScope;
use App\Models\Order;
use App\Models\PancakeCustomerMessage;
use App\Models\PancakeSyncRecord;
use App\Services\CustomerInteractions\CustomerConversationChannel;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class PancakeCustomerChatService
{
    public function __construct(
        protected PancakeConnectionResolver $connections,
    ) {}

    /**
     * @return array{
     *     connected: bool,
     *     reason?: string,
     *     pageId?: ?string,
     *     conversationId?: ?string,
     *     messages: list<array<string, mixed>>,
     *     source: string
     * }
     */
    public function messagesForOrder(Order $order, ?User $viewer = null): array
    {
        $connection = $this->connections->connection();
        $context = $this->conversationContext($order, $connection);

        if (! $context['conversationId']) {
            return [
                'connected' => false,
                'reason' => 'missing_conversation',
                'pageId' => $context['pageId'],
                'conversationId' => null,
                'messages' => [],
                'source' => 'none',
                'realtime' => null,
            ];
        }

        try {
            $payload = $this->connections
                ->client($connection)
                ->conversationMessages($context['pageId'], $context['conversationId'], [
                    'limit' => 80,
                    'offset' => 0,
                ]);

            $messages = $this->normalizeMessages($payload, $order, $connection, $context['pageId'], $context['conversationId']);

            foreach ($messages as $message) {
                $this->storeSnapshot($message, $order, $connection, $context['pageId'], $context['conversationId']);
            }

            return [
                'connected' => true,
                'pageId' => $context['pageId'],
                'conversationId' => $context['conversationId'],
                'messages' => $this->storedMessages($order, $context['conversationId'], $viewer),
                'source' => 'pancake_api',
                'realtime' => [
                    'channel' => CustomerConversationChannel::pancakeForConversation((int) $order->company_id, $context['conversationId']),
                    'event' => '.customer.pancake-message.created',
                    'pollMs' => 7000,
                ],
            ];
        } catch (RuntimeException $exception) {
            $cached = $this->storedMessages($order, $context['conversationId'], $viewer);

            return [
                'connected' => true,
                'reason' => $exception->getMessage(),
                'pageId' => $context['pageId'],
                'conversationId' => $context['conversationId'],
                'messages' => $cached,
                'source' => $cached === [] ? 'error' : 'cache',
                'realtime' => [
                    'channel' => CustomerConversationChannel::pancakeForConversation((int) $order->company_id, $context['conversationId']),
                    'event' => '.customer.pancake-message.created',
                    'pollMs' => 7000,
                ],
            ];
        }
    }

    /** @return array<string, mixed> */
    public function send(Order $order, User $actor, string $content): array
    {
        $connection = $this->connections->connection();
        $context = $this->conversationContext($order, $connection);

        if (! $context['conversationId']) {
            throw new RuntimeException(__('operations.customer_interactions.pancake_missing_conversation'));
        }

        $payload = $this->connections
            ->client($connection)
            ->sendConversationMessage($context['pageId'], $context['conversationId'], $content);

        $messagePayload = $this->extractSentMessagePayload($payload, $content);
        $message = $this->storeSnapshot([
            'externalId' => $this->externalId($messagePayload) ?: 'local_'.Str::uuid()->toString(),
            'direction' => PancakeCustomerMessage::DIRECTION_OUTBOUND,
            'senderId' => (string) $actor->id,
            'senderName' => $actor->name,
            'senderType' => 'saleops_user',
            'message' => $content,
            'attachments' => [],
            'sentAt' => now(),
            'payload' => $messagePayload,
        ], $order, $connection, $context['pageId'], $context['conversationId'], $actor);

        return $this->present($message, $actor);
    }

    /**
     * Cache Pancake message webhook payload and return rows that are new/changed.
     *
     * @param  array<string, mixed>  $payload
     * @return list<PancakeCustomerMessage>
     */
    public function cacheWebhookPayload(IntegrationConnection $connection, array $payload): array
    {
        $context = $this->conversationContextFromPayload($payload, $connection);

        if (! $context['conversationId']) {
            throw new RuntimeException(__('operations.customer_interactions.pancake_missing_conversation'));
        }

        $record = $this->recordForConversation($connection, $context['conversationId']);
        $order = $record?->order
            ?? PancakeCustomerMessage::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('company_id', $connection->company_id)
                ->where('conversation_id', $context['conversationId'])
                ->whereNotNull('order_id')
                ->latest('id')
                ->first()?->order
            ?? $this->orderForPayloadPhone($connection, $payload);

        if (! $order) {
            return [];
        }

        $record ??= $this->upsertConversationRecord($connection, $order, $payload, $context['pageId'], $context['conversationId']);

        $messages = $this->normalizeMessages($payload, $order, $connection, $context['pageId'], $context['conversationId']);
        $changed = [];

        foreach ($messages as $message) {
            $model = $this->storeSnapshot($message, $order, $connection, $context['pageId'], $context['conversationId']);
            if ($model->wasRecentlyCreated || $model->wasChanged(['message', 'attachments', 'sent_at', 'direction', 'sender_name'])) {
                $changed[] = $model;
            }
        }

        return $changed;
    }

    /** @return array{pageId: ?string, conversationId: ?string, record: ?PancakeSyncRecord} */
    protected function conversationContext(Order $order, IntegrationConnection $connection): array
    {
        $record = PancakeSyncRecord::query()
            ->where('order_id', $order->id)
            ->whereIn('external_type', [
                PancakeSyncRecord::TYPE_ORDER,
                PancakeSyncRecord::TYPE_LEAD,
                PancakeSyncRecord::TYPE_CONVERSATION,
                PancakeSyncRecord::TYPE_CUSTOMER,
            ])
            ->latest('last_synced_at')
            ->latest('id')
            ->first();

        $credentials = $connection->credentials ?? [];
        $metadata = $record?->metadata ?? [];
        $payload = $record?->payload ?? [];

        if (! $record) {
            $message = PancakeCustomerMessage::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('company_id', $connection->company_id)
                ->where('order_id', $order->id)
                ->whereNotNull('conversation_id')
                ->latest('sent_at')
                ->latest('id')
                ->first();

            if ($message) {
                return [
                    'pageId' => $message->page_id ?: $this->connections->credential($credentials, 'page_id'),
                    'conversationId' => $message->conversation_id,
                    'record' => null,
                ];
            }
        }

        $pageId = $this->firstScalar([
            Arr::get($metadata, 'page_id'),
            Arr::get($payload, 'page_id'),
            Arr::get($payload, 'page.id'),
            Arr::get($payload, 'conversation.page_id'),
            Arr::get($payload, 'customer.page_id'),
            $this->connections->credential($credentials, 'page_id'),
        ]);

        $conversationId = $this->firstScalar([
            Arr::get($metadata, 'conversation_id'),
            Arr::get($payload, 'conversation_id'),
            Arr::get($payload, 'conversation.id'),
            Arr::get($payload, 'conversation.thread_id'),
            Arr::get($payload, 'thread_id'),
        ]);

        return [
            'pageId' => $pageId,
            'conversationId' => $conversationId,
            'record' => $record,
        ];
    }

    /** @param array<string, mixed> $payload @return list<array<string, mixed>> */
    protected function normalizeMessages(array $payload, Order $order, IntegrationConnection $connection, ?string $pageId, string $conversationId): array
    {
        $rows = $this->messageRows($payload);

        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) use ($order, $connection, $pageId, $conversationId) {
                $sender = Arr::get($row, 'from');
                $sender = is_array($sender) ? $sender : (is_array(Arr::get($row, 'sender')) ? Arr::get($row, 'sender') : []);
                $externalId = $this->externalId($row) ?: sha1(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $message = $this->firstScalar([
                    Arr::get($row, 'message'),
                    Arr::get($row, 'text'),
                    Arr::get($row, 'content'),
                    Arr::get($row, 'body'),
                    Arr::get($row, 'snippet'),
                ]);
                $sentAt = $this->parseTime($this->firstScalar([
                    Arr::get($row, 'created_time'),
                    Arr::get($row, 'created_at'),
                    Arr::get($row, 'inserted_at'),
                    Arr::get($row, 'timestamp'),
                    Arr::get($row, 'sent_at'),
                ]));

                $senderType = $this->firstScalar([
                    Arr::get($row, 'sender_type'),
                    Arr::get($row, 'type'),
                    Arr::get($sender, 'type'),
                ]);

                $isCustomer = $this->isCustomerMessage($row, $senderType);

                return [
                    'externalId' => $externalId,
                    'direction' => $isCustomer ? PancakeCustomerMessage::DIRECTION_INBOUND : PancakeCustomerMessage::DIRECTION_OUTBOUND,
                    'senderId' => $this->firstScalar([
                        Arr::get($sender, 'id'),
                        Arr::get($row, 'sender_id'),
                        Arr::get($row, 'from_id'),
                    ]),
                    'senderName' => $this->firstScalar([
                        Arr::get($sender, 'name'),
                        Arr::get($row, 'sender_name'),
                        $isCustomer ? $order->customer_name : 'Pancake/Sale',
                    ]),
                    'senderType' => $senderType ?: ($isCustomer ? 'customer' : 'page'),
                    'message' => $message,
                    'attachments' => $this->attachments($row),
                    'sentAt' => $sentAt,
                    'payload' => $row,
                ];
            })
            ->sortBy(fn ($message) => optional($message['sentAt'])->timestamp ?? 0)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $message */
    protected function storeSnapshot(
        array $message,
        Order $order,
        IntegrationConnection $connection,
        ?string $pageId,
        string $conversationId,
        ?User $actor = null,
    ): PancakeCustomerMessage {
        $externalId = $message['externalId'] ?: 'hash_'.sha1(json_encode($message['payload'] ?? $message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return PancakeCustomerMessage::query()->updateOrCreate(
            [
                'company_id' => $order->company_id ?? $connection->company_id,
                'conversation_id' => $conversationId,
                'external_message_id' => $externalId,
            ],
            [
                'integration_connection_id' => $connection->id,
                'order_id' => $order->id,
                'sent_by_user_id' => $actor?->id,
                'page_id' => $pageId,
                'direction' => $message['direction'],
                'sender_id' => $message['senderId'],
                'sender_name' => $message['senderName'],
                'sender_type' => $message['senderType'],
                'message' => $message['message'],
                'attachments' => $message['attachments'] ?? [],
                'payload' => $message['payload'] ?? null,
                'sent_at' => $message['sentAt'] instanceof CarbonInterface ? $message['sentAt'] : now(),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    protected function storedMessages(Order $order, string $conversationId, ?User $viewer = null): array
    {
        return PancakeCustomerMessage::query()
            ->where('conversation_id', $conversationId)
            ->oldest('sent_at')
            ->oldest('id')
            ->limit(300)
            ->get()
            ->map(fn (PancakeCustomerMessage $message) => $this->present($message, $viewer))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function present(PancakeCustomerMessage $message, ?User $viewer = null): array
    {
        return [
            'id' => $message->id,
            'externalId' => $message->external_message_id,
            'direction' => $message->direction,
            'isMine' => $message->sent_by_user_id !== null && $viewer?->id === $message->sent_by_user_id,
            'sentByUserId' => $message->sent_by_user_id !== null ? (string) $message->sent_by_user_id : null,
            'conversationId' => $message->conversation_id,
            'pageId' => $message->page_id,
            'senderName' => $message->sender_name,
            'senderType' => $message->sender_type,
            'message' => $message->message,
            'attachments' => $message->attachments ?? [],
            'sentAt' => $message->sent_at?->toISOString(),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    protected function extractSentMessagePayload(array $payload, string $content): array
    {
        $message = Arr::get($payload, 'data')
            ?? Arr::get($payload, 'message')
            ?? Arr::get($payload, 'data.message')
            ?? $payload;

        if (is_array($message)) {
            return $message;
        }

        return ['message' => $content, 'raw' => $payload];
    }

    /** @param array<string, mixed> $payload @return list<array<string, mixed>> */
    protected function messageRows(array $payload): array
    {
        $candidate = Arr::get($payload, 'data.messages')
            ?? Arr::get($payload, 'data.items')
            ?? Arr::get($payload, 'messages')
            ?? Arr::get($payload, 'items')
            ?? Arr::get($payload, 'data')
            ?? Arr::get($payload, 'message')
            ?? $payload;

        if (! is_array($candidate)) {
            return [];
        }

        if ($this->looksLikeMessageRow($candidate)) {
            return [$candidate];
        }

        if (array_is_list($candidate)) {
            return array_values(array_filter($candidate, 'is_array'));
        }

        return [];
    }

    /** @param array<string, mixed> $row */
    protected function looksLikeMessageRow(array $row): bool
    {
        foreach (['id', 'message_id', 'mid', '_id', 'message', 'text', 'content', 'body', 'from', 'sender', 'created_time', 'sent_at'] as $key) {
            if (Arr::has($row, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $payload @return array{pageId: ?string, conversationId: ?string} */
    protected function conversationContextFromPayload(array $payload, IntegrationConnection $connection): array
    {
        $credentials = $connection->credentials ?? [];

        return [
            'pageId' => $this->firstScalar([
                Arr::get($payload, 'page_id'),
                Arr::get($payload, 'page.id'),
                Arr::get($payload, 'data.page_id'),
                Arr::get($payload, 'data.page.id'),
                Arr::get($payload, 'conversation.page_id'),
                Arr::get($payload, 'data.conversation.page_id'),
                $this->connections->credential($credentials, 'page_id'),
            ]),
            'conversationId' => $this->firstScalar([
                Arr::get($payload, 'conversation_id'),
                Arr::get($payload, 'conversation.id'),
                Arr::get($payload, 'conversation.thread_id'),
                Arr::get($payload, 'data.conversation_id'),
                Arr::get($payload, 'data.conversation.id'),
                Arr::get($payload, 'data.conversation.thread_id'),
                Arr::get($payload, 'thread_id'),
                Arr::get($payload, 'data.thread_id'),
            ]),
        ];
    }

    protected function recordForConversation(IntegrationConnection $connection, string $conversationId): ?PancakeSyncRecord
    {
        return PancakeSyncRecord::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('order')
            ->where('company_id', $connection->company_id)
            ->where(function ($query) use ($conversationId) {
                $query->where('external_id', $conversationId)
                    ->orWhere('metadata->conversation_id', $conversationId)
                    ->orWhere('payload->conversation_id', $conversationId)
                    ->orWhere('payload->conversation->id', $conversationId)
                    ->orWhere('payload->conversation->thread_id', $conversationId)
                    ->orWhere('payload->thread_id', $conversationId);
            })
            ->latest('last_synced_at')
            ->latest('id')
            ->first();
    }

    /** @param array<string, mixed> $payload */
    protected function orderForPayloadPhone(IntegrationConnection $connection, array $payload): ?Order
    {
        $phone = $this->customerPhoneFromPayload($payload);
        if (! $phone) {
            return null;
        }

        return Order::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('company_id', $connection->company_id)
            ->where('customer_phone', $phone)
            ->whereNotNull('sale_user_id')
            ->latest('id')
            ->first()
            ?? Order::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('company_id', $connection->company_id)
                ->where('customer_phone', $phone)
                ->latest('id')
                ->first();
    }

    /** @param array<string, mixed> $payload */
    protected function upsertConversationRecord(
        IntegrationConnection $connection,
        Order $order,
        array $payload,
        ?string $pageId,
        string $conversationId,
    ): PancakeSyncRecord {
        return PancakeSyncRecord::query()->updateOrCreate(
            [
                'company_id' => $connection->company_id,
                'external_type' => PancakeSyncRecord::TYPE_CONVERSATION,
                'external_id' => $conversationId,
            ],
            [
                'integration_connection_id' => $connection->id,
                'shop_id' => $this->firstScalar([
                    Arr::get($payload, 'shop_id'),
                    Arr::get($payload, 'data.shop_id'),
                    Arr::get($payload, 'shop.id'),
                    $connection->credentials['shop_id'] ?? null,
                ]),
                'external_code' => null,
                'lead_ingestion_id' => null,
                'order_id' => $order->id,
                'status' => 'conversation_linked',
                'payload' => $payload,
                'metadata' => [
                    'page_id' => $pageId,
                    'conversation_id' => $conversationId,
                    'customer_phone' => $order->customer_phone,
                    'matched_by' => 'phone_or_conversation_webhook',
                ],
                'last_synced_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $payload */
    protected function customerPhoneFromPayload(array $payload): ?string
    {
        $value = $this->firstScalar([
            Arr::get($payload, 'customer_phone'),
            Arr::get($payload, 'phone'),
            Arr::get($payload, 'mobile'),
            Arr::get($payload, 'customer.phone'),
            Arr::get($payload, 'customer.mobile'),
            Arr::get($payload, 'data.customer_phone'),
            Arr::get($payload, 'data.phone'),
            Arr::get($payload, 'data.customer.phone'),
            Arr::get($payload, 'conversation.customer.phone'),
            Arr::get($payload, 'data.conversation.customer.phone'),
            Arr::get($payload, 'sender.phone'),
            Arr::get($payload, 'from.phone'),
        ]);

        if (! $value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 9 ? substr($digits, 0, 20) : null;
    }

    /** @param array<string, mixed> $row */
    protected function externalId(array $row): ?string
    {
        return $this->firstScalar([
            Arr::get($row, 'id'),
            Arr::get($row, 'message_id'),
            Arr::get($row, 'mid'),
            Arr::get($row, '_id'),
        ]);
    }

    /** @param array<string, mixed> $row */
    protected function isCustomerMessage(array $row, ?string $senderType): bool
    {
        $fromPage = Arr::get($row, 'from_page')
            ?? Arr::get($row, 'is_from_page')
            ?? Arr::get($row, 'from.is_page')
            ?? Arr::get($row, 'from.me')
            ?? false;

        if (is_bool($fromPage)) {
            return ! $fromPage;
        }

        $type = Str::of((string) $senderType)->lower()->value();

        return in_array($type, ['customer', 'user', 'client'], true);
    }

    /** @param array<string, mixed> $row @return list<array<string, mixed>> */
    protected function attachments(array $row): array
    {
        $attachments = Arr::get($row, 'attachments')
            ?? Arr::get($row, 'attachment')
            ?? Arr::get($row, 'files')
            ?? [];

        if (! is_array($attachments)) {
            return [];
        }

        return array_values(array_filter($attachments, 'is_array'));
    }

    protected function parseTime(?string $value): CarbonInterface
    {
        if (! filled($value)) {
            return now();
        }

        if (ctype_digit($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return Carbon::createFromTimestamp($timestamp);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }

    /** @param list<mixed> $values */
    protected function firstScalar(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
