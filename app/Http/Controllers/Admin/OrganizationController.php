<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __invoke(): Response
    {
        $teams = Team::query()
            ->with(['leader:id,name,role', 'parent:id,name', 'users:id,name,role,team_id,manager_user_id,is_team_leader'])
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'type' => $team->type->value,
                'type_label' => $team->type->label(),
                'parent' => $team->parent ? ['id' => $team->parent->id, 'name' => $team->parent->name] : null,
                'leader' => $team->leader ? [
                    'id' => $team->leader->id,
                    'name' => $team->leader->name,
                    'role' => $team->leader->role->value,
                ] : null,
                'members_count' => $team->users->count(),
            ])
            ->values();

        $rankings = collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role === UserRole::Admin)
            ->map(function (UserRole $role) {
                $users = User::query()
                    ->where('role', $role)
                    ->with(['team:id,name', 'manager:id,name'])
                    ->get();

                $items = $users->map(function (User $user) {
                    $base = $this->baseOrderQueryForRole($user);
                    $totalOrders = (clone $base)->count();
                    $delivered = (clone $base)->whereIn('delivery_status', ['delivered', 'paid'])->count();
                    $revenue = (clone $base)->sum('total');

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'team' => $user->team?->name,
                        'manager' => $user->manager?->name,
                        'is_team_leader' => $user->is_team_leader,
                        'total_orders' => $totalOrders,
                        'delivered_orders' => $delivered,
                        'revenue' => (int) $revenue,
                    ];
                })->sortByDesc('revenue')->values();

                return [
                    'role' => $role->value,
                    'role_label' => $role->label(),
                    'items' => $items,
                ];
            })
            ->values();

        return Inertia::render('Admin/Organization/Index', [
            'teams' => $teams,
            'rankings' => $rankings,
        ]);
    }

    protected function baseOrderQueryForRole(User $user)
    {
        return match ($user->role) {
            UserRole::Sales => Order::query()->where('sale_user_id', $user->id),
            UserRole::Marketing => Order::query()->where('marketer_user_id', $user->id),
            UserRole::Warehouse, UserRole::Accounting => Order::query()->where('warehouse_id', '>=', 1),
            UserRole::Allocator => Order::query()->where('assigned_at', '>=', now()->subDays(30)),
            default => Order::query()->whereRaw('1 = 0'),
        };
    }
}
