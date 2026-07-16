<?php

namespace App\Observers;

use App\Models\MarketingSource;
use App\Models\Team;
use App\Models\User;
use App\Services\Reporting\ReportDateDirtyTracker;
use Illuminate\Database\Eloquent\Model;

class ReportAccessScopeObserver
{
    public function saved(Model $model): void
    {
        if (! $this->affectsReports($model)) {
            return;
        }

        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function affectsReports(Model $model): bool
    {
        if ($model->wasRecentlyCreated) {
            return true;
        }

        return match (true) {
            $model instanceof User => $model->wasChanged([
                'company_id', 'role', 'team_id', 'manager_user_id', 'is_team_leader',
                'org_level', 'permissions', 'is_owner', 'is_platform_admin',
            ]),
            $model instanceof Team => $model->wasChanged([
                'company_id', 'type', 'leader_user_id', 'parent_id', 'permissions',
            ]),
            $model instanceof MarketingSource => $model->wasChanged([
                'company_id', 'parent_id', 'name', 'product_id', 'marketer_user_id',
                'ad_channel', 'utm_source', 'utm_campaign', 'budget', 'is_active',
                'is_approved', 'lead_allocation',
            ]),
            default => false,
        };
    }

    private function invalidate(Model $model): void
    {
        $companyIds = collect([
            $model->getAttribute('company_id'),
            $model->getOriginal('company_id'),
        ])->filter()->map(fn ($id) => (int) $id)->unique();

        foreach ($companyIds as $companyId) {
            app(ReportDateDirtyTracker::class)->invalidateAllSnapshots($companyId);
        }
    }
}
