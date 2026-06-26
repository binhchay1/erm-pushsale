<?php

namespace App\Jobs\Notifications;

use App\Models\UserNotification;
use App\Services\Translation\TranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fills the missing locale(s) for a free-text notification using the translation
 * provider. Isolated on the `translations` queue so a slow/offline provider
 * never delays delivery of notifications themselves. Fail-safe: if translation
 * is unavailable the notification simply stays in its source language.
 */
class TranslateNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $notificationId)
    {
        $this->onQueue('translations');
    }

    public function handle(TranslationService $translator): void
    {
        if (! $translator->enabled()) {
            return;
        }

        $notification = UserNotification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $data = $notification->data;

        if (! is_array($data) || ($data['variant'] ?? null) !== 'free_text') {
            return;
        }

        $source = (string) ($data['source'] ?? $translator->sourceLocale());
        $titles = (array) ($data['title'] ?? []);
        $messages = (array) ($data['message'] ?? []);

        $sourceTitle = $titles[$source] ?? null;
        $sourceMessage = $messages[$source] ?? null;
        $changed = false;

        foreach ($translator->targetLocales() as $locale) {
            if ($locale === $source) {
                continue;
            }

            if ($sourceTitle !== null && ! isset($titles[$locale])) {
                if (($t = $translator->translate($sourceTitle, $source, $locale)) !== null) {
                    $titles[$locale] = $t;
                    $changed = true;
                }
            }

            if ($sourceMessage !== null && ! isset($messages[$locale])) {
                if (($m = $translator->translate($sourceMessage, $source, $locale)) !== null) {
                    $messages[$locale] = $m;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $data['title'] = $titles;
            $data['message'] = $messages;
            $notification->update(['data' => $data]);
        }
    }
}
