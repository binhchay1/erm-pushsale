<?php

namespace App\Services;

use App\Enums\ClosingStatus;
use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Enums\DiscountMode;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Repositories\WarehouseRepository;

class FilterOptionsService
{
    public function __construct(
        private readonly TeamRepository $teams,
        private readonly ProductRepository $products,
        private readonly WarehouseRepository $warehouses,
        private readonly UserRepository $users,
    ) {}

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
            'teams' => $this->teams->optionsWithType(),
            'products' => $this->products->options(),
            'parentProducts' => $this->products->parentProducts(),
            'warehouses' => $this->warehouses->options(),
            'salesUsers' => $this->users->byRole(UserRole::Sales),
            'marketingUsers' => $this->users->byRole(UserRole::Marketing),
            'sourceTypes' => [
                ['value' => 'standard', 'label' => __('filters.source_standard')],
            ],
            'reconciliationStatuses' => [
                ['value' => 'pending', 'label' => __('filters.reconciliation_pending')],
                ['value' => 'reconciled', 'label' => __('filters.reconciliation_reconciled')],
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

    /**
     * Báo cáo tổng hợp doanh số — tập trung tỷ lệ chốt/doanh thu,
     * không cần ô tìm tên/SĐT. Màn marketing lọc theo NV marketing (không phải sales).
     *
     * @return list<string>
     */
    public function revenueReportFilterFields(?User $user = null, string $department = 'sale'): array
    {
        $fields = ['date_from', 'date_to', 'product_id'];

        if ($department === 'marketing') {
            if ($user?->role !== UserRole::Marketing) {
                $fields[] = 'marketer_id';
            }
        } elseif (! $user?->isSales()) {
            $fields[] = 'sale_id';
        }

        return $fields;
    }

    /** @return list<string> */
    public function ceoReportFilterFields(): array
    {
        return [
            'date_type',
            'date_from',
            'date_to',
            'delivery_status',
            'reconciliation_status',
            'parent_product_id',
            'product_id',
            'discount_mode',
            'per_page',
            'no_closing_date_limit',
        ];
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
    public function warehouseOperationFilterFields(): array
    {
        return ['date_from', 'date_to', 'product_id', 'search', 'warehouse_id'];
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
            'teams' => $this->teams->optionsWithType(),
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
