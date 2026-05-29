<?php

namespace App\Services;

use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Enums\DiscountMode;
use App\Enums\OperationStage;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;

class FilterOptionsService
{
    /** @return array<string, mixed> */
    public function forReports(): array
    {
        return [
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
    }
}
