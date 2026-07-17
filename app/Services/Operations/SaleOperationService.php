<?php

namespace App\Services\Operations;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;
use App\Enums\OperationStage;
use Illuminate\Database\Eloquent\Builder;

class SaleOperationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly SaleOperationConfigurationService $configuration,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter): array
    {
        return $this->buildPaginated($filter);
    }

    /**
     * Phân trang tại SQL. Counts của tab được tính trên cùng bộ lọc nhưng bỏ stage,
     * nên không phải tải toàn bộ bảng orders lên PHP.
     *
     * @return array<string, mixed>
     */
    public function buildPaginated(ReportFilterData $filter): array
    {
        $baseFilter = $filter->withoutOperationStage();
        $baseQuery = $this->orders->queryFiltered($baseFilter);

        $countsQuery = (clone $baseQuery)->withoutEagerLoads()->reorder();
        // queryFiltered() có withCount() nên MySQL ONLY_FULL_GROUP_BY sẽ lỗi nếu
        // câu đếm tab còn giữ orders.* / subselect pending_supplement_packets_count.
        // Reset lại phần SELECT để câu GROUP BY chỉ còn stage_key + aggregate.
        $countsQuery->getQuery()->columns = null;

        $counts = $countsQuery
            ->selectRaw("COALESCE(operation_stage, 'no_operation') as stage_key, COUNT(*) as aggregate")
            ->groupByRaw("COALESCE(operation_stage, 'no_operation')")
            ->pluck('aggregate', 'stage_key')
            ->map(fn ($count) => (int) $count);

        $total = (int) $counts->sum();
        $selectedStage = $filter->operationStage && $filter->operationStage !== 'all'
            ? $filter->operationStage
            : null;

        /** @var Builder $pageQuery */
        $pageQuery = $this->orders->queryFiltered($baseFilter);
        if ($selectedStage === OperationStage::NoOperation->value) {
            $pageQuery->where(function (Builder $query): void {
                $query->whereNull('operation_stage')
                    ->orWhere('operation_stage', OperationStage::NoOperation->value);
            });
        } elseif ($selectedStage) {
            $pageQuery->where('operation_stage', $selectedStage);
        }

        $paginator = $pageQuery
            ->paginate(
                perPage: $filter->perPage,
                columns: ['*'],
                pageName: 'page',
                page: $filter->page,
            )
            ->withQueryString();

        $definitions = $this->configuration->definitions();
        $tabs = collect($definitions)
            ->map(function (array $definition) use ($counts, $total): array {
                $count = (int) ($counts[$definition['value']] ?? 0);

                return $definition + [
                    'status' => $definition['value'],
                    'count' => $count,
                    'total' => $total,
                ];
            })
            ->when($filter->hideZeroStatus, fn ($collection) => $collection->where('count', '>', 0))
            ->values()
            ->all();

        $tabs[] = [
            'status' => 'all',
            'value' => 'all',
            'label' => 'Tất cả',
            'count' => $total,
            'total' => $total,
            'durationMinutes' => 0,
            'level' => 3,
            'color' => '#c1e2f4',
        ];

        return [
            'rows' => [
                'data' => collect($paginator->items())
                    ->map(fn ($order) => OrderOperationPresenter::toArray($order, $this->configuration))
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
            'statusTabs' => $tabs,
        ];
    }
}
