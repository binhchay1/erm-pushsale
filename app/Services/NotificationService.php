<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    public static function push(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $url = null,
    ): UserNotification {
        return UserNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    /** Gửi cùng một thông báo tới tất cả user theo (các) vai trò. */
    public static function pushToRole(
        UserRole|array $roles,
        string $type,
        string $title,
        ?string $message = null,
        ?string $url = null,
    ): void {
        $values = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn (UserRole $r) => $r->value)
            ->all();

        User::query()
            ->whereIn('role', $values)
            ->pluck('id')
            ->each(fn (int $id) => self::push($id, $type, $title, $message, $url));
    }
}
