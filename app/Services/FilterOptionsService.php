<?php

namespace App\Services;

use App\Enums\ClosingStatus;
use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Enums\DiscountMode;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\OrgLevel;
use App\Enums\TeamType;
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
    public function forInventory(?User $user = null): array
    {
        return [
            'warehouses' => $this->warehouses->options(),
            'products' => $this->products->options(),
        ];
    }

    /** @return array<string, mixed> */
    public function forReports(?User $user = null): array
    {
        $allTeams = $this->teams->optionsWithType();
        $salesTeams = $allTeams->filter(fn (Team $team): bool => $team->type === TeamType::Sale)->values();
        $marketingTeams = $allTeams->filter(fn (Team $team): bool => $team->type === TeamType::Marketing)->values();

        $saleLeaderIds = $salesTeams->pluck('leader_user_id')->filter()->unique()->values();
        $marketingLeaderIds = $marketingTeams->pluck('leader_user_id')->filter()->unique()->values();

        $options = [
            'dateTypes' => collect(DateType::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'discountModes' => collect(DiscountMode::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'customerTypes' => [
                ['value' => 'new', 'label' => 'Khách mới'],
                ['value' => 'old', 'label' => 'Khách cũ'],
            ],
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
            'teams' => $allTeams,
            'salesTeams' => $salesTeams,
            'marketingTeams' => $marketingTeams,
            'products' => $this->products->options(),
            'parentProducts' => $this->products->parentProducts(),
            'warehouses' => $this->warehouses->options(),
            'salesUsers' => $this->users->byRole(UserRole::Sales),
            'marketingUsers' => $this->users->byRole(UserRole::Marketing),
            'teamLeaders' => User::query()
                ->where(function ($query) use ($saleLeaderIds) {
                    $query->whereIn('id', $saleLeaderIds)
                        ->orWhere(function ($inner) {
                            $inner->where('is_team_leader', true)->where('role', UserRole::Sales);
                        });
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'marketingTeamLeaders' => User::query()
                ->where(function ($query) use ($marketingLeaderIds) {
                    $query->whereIn('id', $marketingLeaderIds)
                        ->orWhere(function ($inner) {
                            $inner->where('is_team_leader', true)->where('role', UserRole::Marketing);
                        });
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'sourceTypes' => [
                ['value' => 'standard', 'label' => __('filters.source_standard')],
            ],
            'reconciliationStatuses' => [
                ['value' => 'pending', 'label' => __('filters.reconciliation_pending')],
                ['value' => 'reconciled', 'label' => __('filters.reconciliation_reconciled')],
            ],
            'shippingProviders' => collect(config('shipping_partners.providers', []))->map(
                fn (array $meta, string $provider) => ['value' => $provider, 'label' => $meta['label'] ?? $provider]
            )->values(),
            'warehouseCareStatuses' => [
                ['value' => 'waiting', 'label' => 'Chờ care'],
                ['value' => 'calling', 'label' => 'Đang liên hệ'],
                ['value' => 'confirmed', 'label' => 'Đã xác nhận'],
                ['value' => 'reschedule', 'label' => 'Hẹn giao lại'],
                ['value' => 'complaint', 'label' => 'Khiếu nại'],
                ['value' => 'completed', 'label' => 'Hoàn tất'],
            ],
            'printedStatuses' => [
                ['value' => 'printed', 'label' => 'Đã in'],
                ['value' => 'not_printed', 'label' => 'Chưa in'],
            ],
            'depositStatuses' => [
                ['value' => 'with_deposit', 'label' => 'Có cọc'],
                ['value' => 'without_deposit', 'label' => 'Không cọc'],
            ],
            'trackingAlerts' => [
                ['value' => 'missing', 'label' => 'Chưa có mã vận đơn'],
                ['value' => 'has_error', 'label' => 'Có lỗi vận đơn'],
                ['value' => 'stale', 'label' => 'Quá 24h chưa cập nhật'],
            ],
        ];

        $elevated = $user && ($user->isAdmin()
            || $user->is_team_leader
            || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true));

        if ($user?->isSales()) {
            unset($options['marketingUsers'], $options['marketingTeams']);
            $options['teams'] = $salesTeams;

            if (! $elevated) {
                unset($options['salesUsers'], $options['teams'], $options['salesTeams']);
            }
        } elseif ($user?->role === UserRole::Marketing) {
            unset($options['salesUsers'], $options['salesTeams']);
            $options['teams'] = $marketingTeams;

            if (! $elevated) {
                unset($options['marketingUsers'], $options['teams'], $options['marketingTeams']);
            }
        } elseif ($user
            && ! $user->isAdmin()
            && ! in_array($user->role, [UserRole::Warehouse, UserRole::Accounting], true)) {
            unset($options['salesUsers'], $options['marketingUsers'], $options['salesTeams'], $options['marketingTeams']);
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
    public function marketingLeaderStatsFilterFields(): array
    {
        return [
            'date_type',
            'date_from',
            'date_to',
            'discount_mode',
            'delivery_status',
            'marketing_team_leader_id',
            'marketing_team_id',
            'parent_product_id',
            'product_id',
            'reconciliation_status',
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
        return ['date_type', 'date_from', 'date_to', 'product_id', 'search', 'warehouse_id', 'shipping_provider', 'warehouse_care_status', 'printed_status', 'deposit_status', 'tracking_alert', 'care_status', 'sale_id', 'marketer_id', 'team_leader_id', 'team_id', 'marketing_team_leader_id', 'marketing_team_id', 'reconciliation_status', 'min_product_quantity', 'max_product_quantity', 'invoice_status', 'hide_zero_status'];
    }

    /** @return list<string> */
    public function leadsFilterFields(): array
    {
        return ['search'];
    }

    /** @return array<string, mixed> */
    public function forRankings(?User $user = null): array
    {
        $allTeams = $this->teams->optionsWithType();
        $salesTeams = $allTeams->filter(fn (Team $team): bool => $team->type === TeamType::Sale)->values();
        $marketingTeams = $allTeams->filter(fn (Team $team): bool => $team->type === TeamType::Marketing)->values();

        return [
            'discountModes' => collect(DiscountMode::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'customerTypes' => [
                ['value' => 'new', 'label' => 'Khách mới'],
                ['value' => 'old', 'label' => 'Khách cũ'],
            ],
            'operationStages' => collect(OperationStage::cases())->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
            ])->values(),
            'teams' => $allTeams,
            'salesTeams' => $salesTeams,
            'marketingTeams' => $marketingTeams,
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
