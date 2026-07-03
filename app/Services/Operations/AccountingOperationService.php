<?php

namespace App\Services\Operations;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;

class AccountingOperationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        $all = $this->orders->allFiltered($filter);
        $collection = $filter->deliveryStatus && $filter->deliveryStatus !== 'all'
            ? $all->where('delivery_status', $filter->deliveryStatus)
            : $all;

        return [
            'rows' => OrderOperationPresenter::collection($collection),
            'totals' => OrderOperationPresenter::totals($collection),
            'statusTabs' => OrderOperationPresenter::accountingStatusTabs($all, $filter->hideZeroStatus),
        ];
    }
}
