<?php

namespace App\Models\Scopes;

use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantManager::class);

        if (! $tenant->hasContext()) {
            return;
        }

        $companyId = $tenant->id();

        if ($companyId === null) {
            return;
        }

        $builder->where($model->getTable().'.company_id', $companyId);
    }
}
