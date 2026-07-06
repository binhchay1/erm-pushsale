<?php

namespace App\Repositories;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\User;
use App\Services\Reports\ReportScopeResolver;
use App\Support\OrderRevenue;
use Illuminate\Support\Collection;

class MarketingSourceRepository
{
    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    /**
     * Chiến dịch user được xem: tự tạo, được ủy quyền marketer, hoặc team (trưởng nhóm).
     *
     * @param  'all'|'created'|'delegated'  $ownership
     */
    public function visibleCampaignsWithStats(User $user, string $ownership = 'all'): Collection
    {
        $net = OrderRevenue::netAmountSql('orders');

        $query = MarketingSource::query()
            ->whereNull('parent_id')
            ->with(['marketer:id,name', 'creator:id,name', 'approver:id,name', 'rejector:id,name'])
            ->withCount('orders')
            ->select('marketing_sources.*')
            ->selectRaw("COALESCE((
                SELECT SUM({$net})
                FROM orders
                WHERE orders.marketing_source_id = marketing_sources.id
                AND orders.delivery_status IN ('delivered','paid')
            ), 0) as revenue");

        if ($user->role === UserRole::Marketing) {
            $teamIds = $this->scope->allowedMarketerIds($user);
            $isLead = $user->is_team_leader
                || in_array($user->org_level?->value, ['head', 'supervisor'], true);

            $query->where(function ($q) use ($user, $teamIds, $isLead, $ownership) {
                if ($ownership === 'created') {
                    $q->where('created_by_user_id', $user->id);
                } elseif ($ownership === 'delegated') {
                    $q->where('marketer_user_id', $user->id)
                        ->where('created_by_user_id', '!=', $user->id);
                } else {
                    $q->where('created_by_user_id', $user->id)
                        ->orWhere('marketer_user_id', $user->id);
                    if ($isLead) {
                        $q->orWhereIn('marketer_user_id', $teamIds)
                            ->orWhereIn('created_by_user_id', $teamIds);
                    }
                }
            });
        }

        return $query->latest('id')->get();
    }

    /** @deprecated use visibleCampaignsWithStats */
    public function ownedCampaignsWithStats(int $userId): Collection
    {
        return $this->visibleCampaignsWithStats(User::query()->findOrFail($userId), 'created');
    }

    public function findRootByWebhookToken(string $token): ?MarketingSource
    {
        return MarketingSource::query()
            ->whereNull('parent_id')
            ->where('webhook_token', $token)
            ->first();
    }

    /** Chiến dịch gốc cho màn duyệt — admin thấy tất cả, trưởng marketing thấy team. */
    public function rootCampaignsForApproval(?User $viewer = null): Collection
    {
        $query = MarketingSource::query()
            ->whereNull('parent_id')
            ->with([
                'marketer:id,name',
                'creator:id,name',
                'approver:id,name',
                'rejector:id,name',
                'product:id,name,sku,unit_price',
            ])
            ->latest('id');

        if ($viewer && $viewer->role === UserRole::Marketing && ! $viewer->isAdmin()) {
            $allowed = $this->scope->allowedMarketerIds($viewer);
            if ($viewer->org_level?->value !== 'head') {
                $query->where(function ($q) use ($allowed) {
                    $q->whereIn('created_by_user_id', $allowed)
                        ->orWhereIn('marketer_user_id', $allowed);
                });
            }
        }

        return $query->get();
    }
}
