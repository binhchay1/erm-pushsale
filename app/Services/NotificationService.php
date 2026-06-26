<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Jobs\Notifications\SendUserNotification;
use App\Models\MarketingSource;
use App\Models\User;

class NotificationService
{
    /**
     * Queue a notification for a single user. Persisting + broadcasting happens
     * asynchronously on the `notifications` queue.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function push(
        int $userId,
        string $type,
        ?string $title = null,
        ?string $message = null,
        ?string $url = null,
        ?array $data = null,
    ): void {
        SendUserNotification::dispatch($userId, $type, $title, $message, $url, $data);
    }

    /** Gửi cùng một thông báo tới tất cả user theo (các) vai trò. */
    public static function pushToRole(
        UserRole|array $roles,
        string $type,
        ?string $title = null,
        ?string $message = null,
        ?string $url = null,
        ?array $data = null,
    ): void {
        $values = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn (UserRole $r) => $r->value)
            ->all();

        User::query()
            ->whereIn('role', $values)
            ->pluck('id')
            ->each(fn (int $id) => self::push($id, $type, $title, $message, $url, $data));
    }

    public static function notifyLandingApprovalPending(MarketingSource $campaign): void
    {
        $campaign->loadMissing('creator:id,name');
        $creatorName = $campaign->creator?->name ?? 'Marketing';

        self::pushToRole(
            UserRole::Admin,
            'landing_approval',
            null,
            null,
            '/admin/landing-approvals?campaign='.$campaign->id,
            [
                'campaign_name' => $campaign->name,
                'creator' => $creatorName,
            ],
        );
    }

    public static function notifyLandingApproved(MarketingSource $campaign): void
    {
        if (! $campaign->created_by_user_id) {
            return;
        }

        self::push(
            $campaign->created_by_user_id,
            'landing_approved',
            null,
            null,
            '/marketing/campaigns/'.$campaign->id.'/edit',
            [
                'campaign_name' => $campaign->name,
            ],
        );
    }
}
