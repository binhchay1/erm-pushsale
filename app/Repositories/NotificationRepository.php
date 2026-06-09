<?php

namespace App\Repositories;

use App\Models\UserNotification;
use Illuminate\Support\Collection;

class NotificationRepository
{
    public function latestForUser(int $userId, bool $unreadOnly = false, int $limit = 100): Collection
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->when($unreadOnly, fn ($q) => $q->unread())
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAllRead(int $userId): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
