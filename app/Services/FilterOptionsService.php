<?php

namespace App\Services;

use App\Enums\ClosingStatus;
use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Enums\DiscountMode;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;

class FilterOptionsService
{
    /** @return array<string, mixed> */
    public function forReports(?User $user = null): array
    {
        $options = [
            'dateTypes' => collect(DateType::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'discountModes' => collect(DiscountMode::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'deliveryStatuses' => collect(DeliveryStatus::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'operationStages' => collect(OperationStage::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'operationResults' => OperationResult::filterOptions(),
            'closingStatuses' => ClosingStatus::options(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'type']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'parent_id']),
            'parentProducts' => Product::query()->whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']),
            'salesUsers' => User::query()->where('role', UserRole::Sales)->get(['id', 'name', 'email']),
            'marketingUsers' => User::query()->where('role', UserRole::Marketing)->get(['id', 'name', 'email']),
            'sourceTypes' => [
                ['value' => 'standard', 'label' => 'Chuẩn SaleOps'],
            ],
            'reconciliationStatuses' => [
                ['value' => 'pending', 'label' => 'Chờ đối soát'],
                ['value' => 'reconciled', 'label' => 'Đã đối soát'],
            ],
        ];

        if ($user?->isSales()) {
            unset($options['salesUsers'], $options['marketingUsers'], $options['teams']);
        } elseif ($user?->role === UserRole::Marketing) {
            unset($options['salesUsers'], $options['teams']);
        } elseif ($user && ! $user->isAdmin()) {
            unset($options['salesUsers'], $options['marketingUsers']);
        }

        return $options;
    }

    /** @return list<string> */
    public function visibleFilterFields(?User $user = null): array
    {
        $fields = [
            'date_from',
            'date_to',
            'product_id',
            'search',
        ];

        if (! $user?->isSales()) {
            $fields[] = 'sale_id';
        }

        return $fields;
    }

    /** @return list<string> */
    public function marketingDashboardFilterFields(?User $user = null): array
    {
        $fields = ['date_from', 'date_to', 'product_id'];

        if ($user?->role !== UserRole::Marketing) {
            $fields[] = 'marketer_id';
        }

        return $fields;
    }

    /** @return list<string> */
    public function detailReportFilterFields(?User $user = null): array
    {
        $fields = ['date_from', 'date_to', 'product_id'];

        if (! $user?->isSales()) {
            $fields[] = 'sale_id';
        }

        return $fields;
    }

    /** @return list<string> */
    public function marketingCampaignReportFilterFields(?User $user = null): array
    {
        $fields = ['date_from', 'date_to', 'product_id'];

        if ($user?->role !== UserRole::Marketing) {
            $fields[] = 'marketer_id';
        }

        return $fields;
    }

    /** @return list<string> */
    public function saleOperationFilterFields(): array
    {
        return [
            'date_from',
            'date_to',
            'product_id',
            'operation_result',
            'search',
        ];
    }

    /** @return list<string> */
    public function inventoryFilterFields(): array
    {
        return ['search', 'warehouse_id', 'product_id'];
    }

    /** @return list<string> */
    public function leadsFilterFields(): array
    {
        return ['search'];
    }

    /** @return array<string, mixed> */
    public function forRankings(?User $user = null): array
    {
        return [
            'discountModes' => collect(DiscountMode::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'operationStages' => collect(OperationStage::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'type']),
            'teamLeaders' => User::query()
                ->where(function ($q) {
                    $q->where('is_team_leader', true)
                        ->orWhereIn('id', Team::query()->whereNotNull('leader_user_id')->pluck('leader_user_id'));
                })
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
