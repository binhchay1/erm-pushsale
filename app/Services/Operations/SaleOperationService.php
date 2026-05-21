<?php

namespace App\Services\Operations;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;

class SaleOperationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        $collection = $this->orders->allFiltered($filter);

        if ($filter->operationStage && $filter->operationStage !== 'all') {
            $collection = $collection->where('operation_stage', $filter->operationStage);
        }

        return [
            'rows' => OrderOperationPresenter::collection($collection),
            'statusTabs' => OrderOperationPresenter::statusTabs(
                $this->orders->allFiltered($filter),
                $filter->hideZeroStatus,
            ),
        ];
    }
}
