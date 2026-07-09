<?php

namespace App\Jobs\Notifications;

use App\Events\UserNotificationCreated;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists a single notification and broadcasts it. Runs on the dedicated
 * `notifications` queue so request latency / role fan-out never blocks the user.
 */
class SendUserNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public int $userId,
        public string $type,
        public ?string $title = null,
        public ?string $message = null,
        public ?string $url = null,
        public ?array $data = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.notifications', 'notifications'));
    }

    public function handle(): void
    {
        $companyId = User::query()->whereKey($this->userId)->value('company_id');

        app(TenantManager::class)->forCompany($companyId, function (): void {
            $this->persist();
        });
    }

    private function persist(): void
    {
        $data = $this->data;
        $title = $this->title ?? '';
        $message = $this->message;

        // Free text (no i18n key): keep the source text in the columns and wrap
        // it in a translatable `data` envelope so it can become bilingual.
        $isFreeText = $data === null && ($this->title !== null || $this->message !== null);

        if ($isFreeText) {
            $source = (string) config('translation.source_locale', 'vi');
            $data = [
                'variant' => 'free_text',
                'source' => $source,
                'title' => array_filter([$source => $this->title], fn ($v) => $v !== null && $v !== ''),
                'message' => array_filter([$source => $this->message], fn ($v) => $v !== null && $v !== ''),
            ];
        } elseif ($data !== null) {
            // Keyed notification: title/message resolved client-side from data.
            $title = '';
            $message = null;
        }

        $notification = UserNotification::query()->create([
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'url' => $this->url,
        ]);

        if ($isFreeText) {
            TranslateNotificationJob::dispatch($notification->id);
        }

        // Broadcast là tác dụng phụ — đã lưu DB rồi nên broadcast lỗi (Reverb
        // chưa chạy / sai cấu hình) không được làm fail job → tránh tạo trùng.
        try {
            event(new UserNotificationCreated($notification));
        } catch (Throwable $e) {
            Log::warning('Notification broadcast failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
