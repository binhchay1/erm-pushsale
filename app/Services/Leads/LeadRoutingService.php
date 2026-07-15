<?php

namespace App\Services\Leads;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadRoutingService
{
    public function assignSalesUser(?MarketingSource $marketingSource = null): ?User
    {
        $connection = $marketingSource?->relationLoaded('landingConnection')
            ? $marketingSource->landingConnection
            : $marketingSource?->landingConnection()->with('sales.user')->first();

        if ($connection && $connection->sales->where('is_active', true)->isNotEmpty()) {
            return $this->fromLandingConnection($connection);
        }

        /** @var Collection<int, User> $salesUsers */
        $salesUsers = User::query()
            ->where('role', UserRole::Sales)
            ->orderBy('id')
            ->get();

        if ($salesUsers->isEmpty()) {
            return null;
        }

        $strategy = config('saleops.lead_routing.strategy', 'round_robin');

        return match ($strategy) {
            'least_load' => $this->leastLoad($salesUsers),
            'random' => $salesUsers->random(),
            default => $this->roundRobin($salesUsers, 'lead_routing:last_sale_user_id'),
        };
    }

    private function fromLandingConnection(LandingConnection $connection): ?User
    {
        $assignments = $connection->sales
            ->where('is_active', true)
            ->filter(fn ($assignment) => $assignment->user?->role === UserRole::Sales)
            ->sortBy([['priority', 'asc'], ['id', 'asc']]);

        if ($assignments->isEmpty()) {
            return null;
        }

        /** @var Collection<int, User> $users */
        $users = $assignments->pluck('user')->filter()->unique('id')->values();

        return match ($connection->allocation_method) {
            'priority' => $this->priority($assignments),
            'round_robin' => $this->roundRobin($users, 'lead_routing:landing_connection:'.$connection->id),
            default => $this->roundRobin($users, 'lead_routing:landing_connection:'.$connection->id),
        };
    }

    private function priority(Collection $assignments): ?User
    {
        $firstPriority = (int) $assignments->min('priority');
        $users = $assignments->where('priority', $firstPriority)->pluck('user')->filter()->values();

        return $users->count() > 1 ? $this->leastLoad($users) : $users->first();
    }

    /** @param Collection<int, User> $salesUsers */
    protected function roundRobin(Collection $salesUsers, string $cacheKey): User
    {
        $ids = $salesUsers->pluck('id')->values();
        $lastId = cache($cacheKey);
        $nextId = $ids->first();

        if ($lastId !== null) {
            $index = $ids->search((int) $lastId);
            if ($index !== false) {
                $nextId = $ids[($index + 1) % $ids->count()];
            }
        }

        cache([$cacheKey => $nextId], now()->addDays(7));

        return $salesUsers->firstWhere('id', $nextId);
    }

    /** @param Collection<int, User> $salesUsers */
    protected function leastLoad(Collection $salesUsers): User
    {
        $today = now()->startOfDay();

        return $salesUsers
            ->sortBy(fn (User $user) => Order::query()
                ->where('sale_user_id', $user->id)
                ->where('assigned_at', '>=', $today)
                ->count())
            ->first();
    }
}
