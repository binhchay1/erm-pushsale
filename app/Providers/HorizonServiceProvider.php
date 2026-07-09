<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only platform administrators may inspect queue payloads, failures and
     * runtime metadata exposed by the Horizon dashboard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function (User $user): bool {
            return $user->canManagePlatform();
        });
    }
}
