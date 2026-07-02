<?php

namespace App\Services\Marketing;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Reports\ReportScopeResolver;
use App\Support\ActivityLogger;
use Illuminate\Validation\ValidationException;

class CampaignApprovalService
{
    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    public function canApprove(User $user, MarketingSource $campaign): bool
    {
        if ($campaign->parent_id !== null || $campaign->is_approved) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Marketing) {
            return false;
        }

        if ($campaign->created_by_user_id === $user->id) {
            return false;
        }

        if ($user->org_level === OrgLevel::Head) {
            return true;
        }

        if ($user->is_team_leader || $user->org_level === OrgLevel::Supervisor) {
            $allowed = $this->scope->allowedMarketerIds($user);

            return in_array((int) $campaign->created_by_user_id, $allowed, true)
                || in_array((int) $campaign->marketer_user_id, $allowed, true);
        }

        return false;
    }

    public function canViewApprovals(User $user): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Marketing) {
            return false;
        }

        return $user->org_level === OrgLevel::Head
            || $user->org_level === OrgLevel::Supervisor
            || $user->is_team_leader;
    }

    public function approve(User $actor, MarketingSource $campaign): MarketingSource
    {
        abort_unless($this->canApprove($actor, $campaign), 403);

        if (! $campaign->product_id || ! $campaign->webhook_token) {
            throw ValidationException::withMessages([
                'campaign' => __('messages.campaign_approval.incomplete'),
            ]);
        }

        $campaign->update([
            'is_approved' => true,
            'approved_by_user_id' => $actor->id,
            'approved_at' => now(),
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        $fresh = $campaign->fresh(['creator', 'marketer', 'approver']);
        NotificationService::notifyLandingApproved($fresh);

        ActivityLogger::log(
            ActivityLogger::CAMPAIGN_APPROVED,
            $fresh,
            [
                'marketer_user_id' => $fresh->marketer_user_id,
                'created_by_user_id' => $fresh->created_by_user_id,
            ],
            $fresh->name,
            $actor,
        );

        return $fresh;
    }

    public function reject(User $actor, MarketingSource $campaign, string $reason): MarketingSource
    {
        abort_unless($this->canApprove($actor, $campaign), 403);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('messages.campaign_approval.reason_required'),
            ]);
        }

        $campaign->update([
            'is_approved' => false,
            'rejected_by_user_id' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $fresh = $campaign->fresh(['creator', 'marketer', 'rejector']);

        ActivityLogger::log(
            ActivityLogger::CAMPAIGN_REJECTED,
            $fresh,
            ['reason' => $reason],
            $fresh->name,
            $actor,
        );

        return $fresh;
    }
}
