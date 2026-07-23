<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

final class ActivityLogger
{
    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const AUTH_LOGIN_SUCCESS = 'auth.login.success';

    public const AUTH_LOGIN_FAILED = 'auth.login.failed';

    public const AUTH_LOGIN_BLOCKED = 'auth.login.blocked';

    public const AUTH_LOGOUT = 'auth.logout';

    public const CAMPAIGN_CREATED = 'campaign.created';

    public const CAMPAIGN_UPDATED = 'campaign.updated';

    public const CAMPAIGN_DELETED = 'campaign.deleted';

    public const CAMPAIGN_APPROVED = 'campaign.approved';

    public const CAMPAIGN_REJECTED = 'campaign.rejected';

    public const ORDER_CLOSED = 'order.closed';

    public const ORDER_UPDATED = 'order.updated';

    public const ORDER_CALL_LOGGED = 'order.call_logged';

    public const INVENTORY_MOVEMENT_APPROVED = 'inventory.movement_approved';

    public const LEAD_INGESTED = 'lead.ingested';

    public const MARKETING_DAILY_METRICS_UPDATED = 'marketing.daily_metrics_updated';

    public const DATA_FILTER_SEARCHED = 'data_filter.searched';

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?string $subjectLabel = null,
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= auth()->user();
        $tenant = app(TenantManager::class);

        return ActivityLog::query()->create([
            'company_id' => $tenant->hasContext() ? $tenant->id() : ($actor?->company_id),
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? self::resolveSubjectLabel($subject),
            'properties' => $properties !== [] ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    private static function resolveSubjectLabel(?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        return match (true) {
            isset($subject->name) && is_string($subject->name) => $subject->name,
            isset($subject->order_code) => (string) $subject->order_code,
            isset($subject->email) => (string) $subject->email,
            default => class_basename($subject).':'.$subject->getKey(),
        };
    }
}
