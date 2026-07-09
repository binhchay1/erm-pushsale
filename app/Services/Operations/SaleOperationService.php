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

    /**
     * Danh sách hồ sơ khách hàng dùng phân trang phía server để không tải toàn bộ
     * đơn hàng vào bộ nhớ khi dữ liệu tăng lớn.
     *
     * @return array<string, mixed>
     */
    public function buildPaginated(ReportFilterData $filter): array
    {
        $paginator = $this->orders
            ->queryFiltered($filter)
            ->paginate(
                perPage: $filter->perPage,
                columns: ['*'],
                pageName: 'page',
                page: $filter->page,
            )
            ->withQueryString();

        return [
            'rows' => [
                'data' => collect($paginator->items())
                    ->map(fn ($order) => OrderOperationPresenter::toArray($order))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ];
    }
}
