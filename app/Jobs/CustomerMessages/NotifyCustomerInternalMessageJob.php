<?php

namespace App\Jobs\CustomerMessages;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\CustomerInternalMessage;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyCustomerInternalMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 45;

    public function __construct(public int $messageId)
    {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.messages', 'messages'));
    }

    public function handle(): void
    {
        $message = CustomerInternalMessage::query()->find($this->messageId);
        if (! $message) {
            return;
        }

        app(TenantManager::class)->forCompany($message->company_id, function () use ($message): void {
            $this->notifyParticipants($message);
        });
    }

    private function notifyParticipants(CustomerInternalMessage $message): void
    {
        $order = $message->order_id ? Order::query()->find($message->order_id) : null;
        $phone = $message->customer_phone;

        $targetIds = collect();

        if ($order?->sale_user_id) {
            $targetIds->push((int) $order->sale_user_id);
        }
        if ($order?->marketer_user_id) {
            $targetIds->push((int) $order->marketer_user_id);
        }

        // Các bộ phận được phép tương tác nội bộ với khách hàng.
        User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Warehouse->value])
            ->pluck('id')
            ->each(fn ($id) => $targetIds->push((int) $id));

        // User được cấp customers:full cũng nhận notification, kể cả không thuộc role mặc định.
        User::query()->get(['id', 'permissions', 'role'])->each(function (User $user) use ($targetIds) {
            if ($user->allows(PermissionArea::Customers, PermissionLevel::Full)) {
                $targetIds->push((int) $user->id);
            }
        });

        $targetIds = $targetIds
            ->unique()
            ->reject(fn (int $id) => $id === (int) $message->author_user_id)
            ->values();

        $url = '/customers?phone='.urlencode($phone);
        $customer = $order?->customer_name ?: $phone;
        $title = 'Tin nhắn nội bộ khách hàng';
        $body = sprintf('%s vừa nhắn về khách %s: %s', $message->author_name, $customer, str($message->message)->limit(120));

        $targetIds->each(function (int $userId) use ($title, $body, $url, $message): void {
            try {
                NotificationService::push($userId, 'customer_internal_message', $title, $body, $url);
            } catch (\Throwable $e) {
                Log::warning('Customer message notification failed', [
                    'message_id' => $message->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
