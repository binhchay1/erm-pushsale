<?php

namespace App\Services\Customers;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;

final class CustomerProfileOptionsService
{
    /** @return array<string, mixed> */
    public function build(?User $currentUser): array
    {
        $saleTeams = Team::query()
            ->where('type', TeamType::Sale->value)
            ->orderBy('name')
            ->get(['id', 'name', 'leader_user_id']);

        $marketingTeams = Team::query()
            ->where('type', TeamType::Marketing->value)
            ->orderBy('name')
            ->get(['id', 'name', 'leader_user_id']);

        $sales = User::query()
            ->where('role', UserRole::Sales->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team_id', 'manager_user_id', 'is_team_leader']);

        $marketers = User::query()
            ->where('role', UserRole::Marketing->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team_id', 'manager_user_id', 'is_team_leader']);

        return [
            'dateTypes' => [
                ['value' => 'data_arrival', 'label' => 'Ngày data về'],
                ['value' => 'sale_received_data', 'label' => 'Ngày sale nhận data'],
                ['value' => 'sale_operation_date', 'label' => 'Ngày sale tác nghiệp'],
                ['value' => 'closing_date', 'label' => 'Ngày chốt đơn'],
                ['value' => 'expected_delivery_date', 'label' => 'Ngày muốn nhận hàng'],
                ['value' => 'updated_at', 'label' => 'Ngày cập nhật'],
            ],
            'careStatuses' => [
                ['value' => 'care', 'label' => 'Care đơn'],
                ['value' => 'not_care', 'label' => 'Không care đơn'],
            ],
            'closingStatuses' => ClosingStatus::options(),
            'sources' => MarketingSource::query()
                ->with('marketer:id,name,email')
                ->orderBy('name')
                ->get(['id', 'name', 'marketer_user_id', 'ad_channel'])
                ->map(fn (MarketingSource $source) => [
                    'value' => (string) $source->id,
                    'label' => $source->name,
                    'marketer' => $source->marketer?->name,
                ])
                ->values(),
            'saleLeaders' => $this->leaders($sales, $saleTeams),
            'saleTeams' => $saleTeams->map(fn (Team $team) => [
                'value' => (string) $team->id,
                'label' => $team->name,
                'leaderId' => $team->leader_user_id ? (string) $team->leader_user_id : null,
            ])->values(),
            'sales' => $sales->map(fn (User $user) => $this->userOption($user))->values(),
            'marketingLeaders' => $this->leaders($marketers, $marketingTeams),
            'marketingTeams' => $marketingTeams->map(fn (Team $team) => [
                'value' => (string) $team->id,
                'label' => $team->name,
                'leaderId' => $team->leader_user_id ? (string) $team->leader_user_id : null,
            ])->values(),
            'marketers' => $marketers->map(fn (User $user) => $this->userOption($user))->values(),
            'operationStages' => app(\App\Services\Operations\SaleOperationConfigurationService::class)
                ->filterOptions(includeNoOperation: true),
            'operationResults' => OperationResult::filterOptions(),
            'deliveryStatuses' => collect(DeliveryStatus::cases())->map(fn (DeliveryStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku'])
                ->map(fn (Product $product) => [
                    'value' => (string) $product->id,
                    'label' => $product->name.($product->sku ? ' ('.$product->sku.')' : ''),
                ])->values(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Warehouse $warehouse) => ['value' => (string) $warehouse->id, 'label' => $warehouse->name])
                ->values(),
            'reconciliationStatuses' => [
                ['value' => 'pending', 'label' => 'Chưa đối soát'],
                ['value' => 'reconciled', 'label' => 'Đã đối soát'],
            ],
            'duplicateStatuses' => [
                ['value' => 'duplicate', 'label' => 'Trùng số'],
                ['value' => 'unique', 'label' => 'Không trùng số'],
            ],
            'customerTypes' => [
                ['value' => 'new', 'label' => 'Khách mới'],
                ['value' => 'returning', 'label' => 'Khách cũ'],
            ],
            'allocationStatuses' => [
                ['value' => 'assigned', 'label' => 'Đã phân bổ'],
                ['value' => 'unassigned', 'label' => 'Chưa phân bổ'],
            ],
            'shippingMethods' => [
                ['value' => 'cod', 'label' => 'COD'],
                ['value' => 'self_delivery', 'label' => 'Tự giao'],
                ['value' => 'pickup', 'label' => 'Khách nhận tại kho'],
            ],
            'permissions' => [
                'canWriteMessages' => (bool) $currentUser?->allows('customers', 'full'),
                'canBulkManage' => (bool) $currentUser?->allows('customers', 'full'),
                'canDeleteHistory' => (bool) $currentUser?->isAdmin(),
            ],
        ];
    }

    /** @param \Illuminate\Support\Collection<int, User> $users
     *  @param \Illuminate\Support\Collection<int, Team> $teams
     */
    private function leaders($users, $teams)
    {
        $leaderIds = $teams->pluck('leader_user_id')->filter()->map(fn ($id) => (int) $id);

        return $users
            ->filter(fn (User $user) => $user->is_team_leader || $leaderIds->contains($user->id))
            ->unique('id')
            ->map(fn (User $user) => $this->userOption($user))
            ->values();
    }

    /** @return array<string, mixed> */
    private function userOption(User $user): array
    {
        return [
            'value' => (string) $user->id,
            'label' => $user->name,
            'email' => $user->email,
            'teamId' => $user->team_id ? (string) $user->team_id : null,
            'managerId' => $user->manager_user_id ? (string) $user->manager_user_id : null,
        ];
    }
}
