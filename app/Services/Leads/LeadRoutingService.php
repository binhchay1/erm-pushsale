<?php

namespace App\Services\Leads;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadRoutingService
{
    public function assignSalesUser(): ?User
    {
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
            default => $this->roundRobin($salesUsers),
        };
    }

    /** @param  Collection<int, User>  $salesUsers */
    protected function roundRobin(Collection $salesUsers): User
    {
        $ids = $salesUsers->pluck('id')->values();
        $lastId = cache('lead_routing:last_sale_user_id');
        $nextId = $ids->first();

        if ($lastId !== null) {
            $index = $ids->search((int) $lastId);
            if ($index !== false) {
                $nextId = $ids[($index + 1) % $ids->count()];
            }
        }

        cache(['lead_routing:last_sale_user_id' => $nextId], now()->addDays(7));

        return $salesUsers->firstWhere('id', $nextId);
    }

    /** @param  Collection<int, User>  $salesUsers */
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
