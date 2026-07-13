<?php

namespace App\Services\Reports;

use App\Data\MarketingRankingFilterData;
use App\Enums\ClosingStatus;
use App\Enums\DiscountMode;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Support\LeadContactMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketingLeaderboardService
{
    /** @return array<string, mixed> */
    public function build(MarketingRankingFilterData $filter): array
    {
        $users = $this->userQuery($filter)->with('team:id,name')->get();
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->values();

        $ingestions = LeadContactMetrics::applyCountableScope(LeadIngestion::query())
            ->with([
                'marketingSource:id,parent_id,marketer_user_id',
                'marketingSource.parent:id,marketer_user_id',
                'order:id,marketer_user_id,is_returning_customer',
            ])
            ->whereBetween('lead_ingestions.created_at', [$filter->dateFrom, $filter->dateTo])
            ->where(function (Builder $query) use ($userIds): void {
                $query->whereHas('order', fn (Builder $order) => $order->whereIn('marketer_user_id', $userIds))
                    ->orWhereHas('marketingSource', fn (Builder $source) => $source->whereIn('marketer_user_id', $userIds))
                    ->orWhereHas('marketingSource.parent', fn (Builder $source) => $source->whereIn('marketer_user_id', $userIds));
            })
            ->get();

        $orders = Order::query()
            ->with(['items:id,order_id,quantity,unit_price'])
            ->whereIn('marketer_user_id', $userIds)
            ->where(function (Builder $closed): void {
                $closed->whereNotNull('closed_at')
                    ->orWhere('closing_status', ClosingStatus::Closed->value);
            })
            ->where(function (Builder $dated) use ($filter): void {
                $dated->whereBetween('closed_at', [$filter->dateFrom, $filter->dateTo])
                    ->orWhere(function (Builder $legacy) use ($filter): void {
                        $legacy->whereNull('closed_at')
                            ->where('closing_status', ClosingStatus::Closed->value)
                            ->whereBetween('updated_at', [$filter->dateFrom, $filter->dateTo]);
                    });
            })
            ->when($filter->operationScope === 'next', fn (Builder $q) => $q->whereNotNull('next_operation_at'))
            ->when($filter->operationScope === 'required', fn (Builder $q) => $q->whereNotNull('operation_stage'))
            ->get();

        $rows = $users->map(function (User $user) use ($ingestions, $orders, $filter): array {
            $contacts = $ingestions->filter(function (LeadIngestion $lead) use ($user): bool {
                $marketerId = $lead->order?->marketer_user_id
                    ?? $lead->marketingSource?->marketer_user_id
                    ?? $lead->marketingSource?->parent?->marketer_user_id;

                return (int) $marketerId === (int) $user->id;
            });
            $userOrders = $orders->where('marketer_user_id', $user->id);

            $newContacts = $contacts->filter(fn (LeadIngestion $lead): bool => ! (bool) $lead->order?->is_returning_customer)->count();
            $oldContacts = $contacts->filter(fn (LeadIngestion $lead): bool => (bool) $lead->order?->is_returning_customer)->count();
            $newOrders = $userOrders->where('is_returning_customer', false);
            $oldOrders = $userOrders->where('is_returning_customer', true);

            $newRevenue = $this->revenue($newOrders, $filter->discountMode);
            $oldRevenue = $this->revenue($oldOrders, $filter->discountMode);
            $subtotalRevenue = $newRevenue + $oldRevenue;
            $discount = (int) $userOrders->sum('discount');
            $codCollected = (int) $userOrders->sum('shipping_fee_collected');
            $codServiceFee = (int) $userOrders->sum(fn (Order $order): int => (int) $order->cod_fee + (int) $order->carrier_service_fee);
            $finalRevenue = max(0, $subtotalRevenue + $codCollected - $codServiceFee);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => strstr((string) $user->email, '@', true) ?: $user->email,
                'team' => $user->team?->name,
                'avatar' => $user->avatarUrl(),
                'initials' => $user->initials(),
                'newContacts' => $newContacts,
                'newClosedOrders' => $newOrders->count(),
                'newClosingRate' => $newContacts > 0 ? round($newOrders->count() / $newContacts * 100, 2) : 0,
                'newProductQuantity' => $this->productQuantity($newOrders),
                'newRevenue' => $newRevenue,
                'oldContacts' => $oldContacts,
                'oldClosedOrders' => $oldOrders->count(),
                'oldClosingRate' => $oldContacts > 0 ? round($oldOrders->count() / $oldContacts * 100, 2) : 0,
                'oldProductQuantity' => $this->productQuantity($oldOrders),
                'oldRevenue' => $oldRevenue,
                'subtotalRevenue' => $subtotalRevenue,
                'discount' => $discount,
                'codCollected' => $codCollected,
                'codServiceFee' => $codServiceFee,
                'finalRevenue' => $finalRevenue,
                'orders' => $userOrders->count(),
            ];
        })->sortByDesc('finalRevenue')->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });

        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $filter->perPage));
        $page = min($filter->page, $lastPage);
        $pageRows = $rows->slice(($page - 1) * $filter->perPage, $filter->perPage)->values();

        return [
            'top' => $rows->take(10)->values()->all(),
            'rows' => $pageRows->all(),
            'totalRow' => $this->totals($rows),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $filter->perPage,
                'total' => $total,
                'from' => $total === 0 ? 0 : (($page - 1) * $filter->perPage) + 1,
                'to' => min($page * $filter->perPage, $total),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        $teams = Team::query()
            ->where('type', TeamType::Marketing->value)
            ->orderBy('name')
            ->get(['id', 'name', 'leader_user_id']);
        $leaders = User::query()
            ->where('role', UserRole::Marketing->value)
            ->where(function (Builder $q) use ($teams): void {
                $q->where('is_team_leader', true)
                    ->orWhereIn('id', $teams->pluck('leader_user_id')->filter());
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'teams' => $teams->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name, 'leader_user_id' => $team->leader_user_id])->values(),
            'teamLeaders' => $leaders->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'label' => $user->name.' ('.(strstr((string) $user->email, '@', true) ?: $user->email).')',
            ])->values(),
            'discountModes' => [
                ['value' => DiscountMode::AfterDiscount->value, 'label' => 'Sau chiết khấu'],
                ['value' => DiscountMode::BeforeDiscount->value, 'label' => 'Trước chiết khấu'],
            ],
            'operationScopes' => [
                ['value' => 'required', 'label' => 'Tác nghiệp cần'],
                ['value' => 'next', 'label' => 'Tác nghiệp tiếp'],
            ],
        ];
    }

    /** @return Builder<User> */
    private function userQuery(MarketingRankingFilterData $filter): Builder
    {
        return User::query()
            ->where('role', UserRole::Marketing->value)
            ->when($filter->teamId, fn (Builder $q, int $id) => $q->where('team_id', $id))
            ->when($filter->teamLeaderId, function (Builder $q, int $id): void {
                $q->where(function (Builder $member) use ($id): void {
                    $member->where('manager_user_id', $id)
                        ->orWhere('id', $id)
                        ->orWhereHas('team', fn (Builder $team) => $team->where('leader_user_id', $id));
                });
            })
            ->orderBy('name');
    }

    /** @param Collection<int, Order> $orders */
    private function revenue(Collection $orders, DiscountMode $mode): int
    {
        return (int) $orders->sum(function (Order $order) use ($mode): int {
            return $mode === DiscountMode::BeforeDiscount
                ? max((int) $order->subtotal, (int) $order->items->sum(fn ($item) => (int) $item->quantity * (int) $item->unit_price))
                : $order->effectiveRevenue();
        });
    }

    /** @param Collection<int, Order> $orders */
    private function productQuantity(Collection $orders): int
    {
        return (int) $orders->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
    }

    /** @param Collection<int, array<string, mixed>> $rows @return array<string, int|float> */
    private function totals(Collection $rows): array
    {
        $newContacts = (int) $rows->sum('newContacts');
        $newClosed = (int) $rows->sum('newClosedOrders');
        $oldContacts = (int) $rows->sum('oldContacts');
        $oldClosed = (int) $rows->sum('oldClosedOrders');

        return [
            'newContacts' => $newContacts,
            'newClosedOrders' => $newClosed,
            'newClosingRate' => $newContacts > 0 ? round($newClosed / $newContacts * 100, 2) : 0,
            'newProductQuantity' => (int) $rows->sum('newProductQuantity'),
            'newRevenue' => (int) $rows->sum('newRevenue'),
            'oldContacts' => $oldContacts,
            'oldClosedOrders' => $oldClosed,
            'oldClosingRate' => $oldContacts > 0 ? round($oldClosed / $oldContacts * 100, 2) : 0,
            'oldProductQuantity' => (int) $rows->sum('oldProductQuantity'),
            'oldRevenue' => (int) $rows->sum('oldRevenue'),
            'subtotalRevenue' => (int) $rows->sum('subtotalRevenue'),
            'discount' => (int) $rows->sum('discount'),
            'codCollected' => (int) $rows->sum('codCollected'),
            'codServiceFee' => (int) $rows->sum('codServiceFee'),
            'finalRevenue' => (int) $rows->sum('finalRevenue'),
        ];
    }
}
