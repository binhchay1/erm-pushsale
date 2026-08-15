<?php

namespace App\Services\Operations;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;
use App\Enums\OperationStage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class SaleOperationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly SaleOperationConfigurationService $configuration,
        private readonly SalesVisibilityScope $visibility,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter, ?User $viewer = null): array
    {
        return $this->buildPaginated($filter, $viewer);
    }

    /**
     * Phân trang tại SQL. Counts của tab được tính trên cùng bộ lọc nhưng bỏ stage,
     * nên không phải tải toàn bộ bảng orders lên PHP.
     *
     * @return array<string, mixed>
     */
    public function buildPaginated(ReportFilterData $filter, ?User $viewer = null): array
    {
        $viewer = $viewer ?? auth()->user();
        $baseFilter = $filter->withoutOperationStage();

        // Count tab tác nghiệp bằng query sạch, không dùng repository queryFiltered()
        // vì query đó eager load + withCount pendingSupplementPackets cho bảng chính.
        // Nếu xóa columns của query có withCount nhưng không xóa select bindings, PDO sẽ
        // bind nhầm false/true của subquery vào whereBetween (thành BETWEEN 0 AND 1)
        // và gây SQLSTATE[HY093] trên /sales/workspace.
        try {
            $countQuery = Order::query()
                ->applyReportFilter($baseFilter);
            if ($viewer instanceof User) {
                $this->visibility->applyToOrders($countQuery, $viewer);
            }
            $countQuery = $countQuery
                ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
                ->cloneWithoutBindings(['select', 'order']);

            $counts = $countQuery
                ->selectRaw("COALESCE(operation_stage, 'no_operation') as stage_key, COUNT(*) as aggregate")
                ->groupByRaw("COALESCE(operation_stage, 'no_operation')")
                ->pluck('aggregate', 'stage_key')
                ->map(fn ($count) => (int) $count);
        } catch (\Throwable $exception) {
            Log::warning('sale_workspace.stage_count_failed', [
                'message' => $exception->getMessage(),
                'filter' => $baseFilter->toInertia(),
            ]);
            $counts = collect();
        }

        $total = (int) $counts->sum();
        $selectedStage = $filter->operationStage && $filter->operationStage !== 'all'
            ? $filter->operationStage
            : null;

        /** @var Builder $pageQuery */
        $pageQuery = $this->orders->queryFiltered($baseFilter);
        if ($viewer instanceof User) {
            $this->visibility->applyToOrders($pageQuery, $viewer);
        }
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

        $definitions = $this->configuration->activeDefinitions(includeNoOperation: true);
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
            'level' => '',
            'color' => '#c1e2f4',
        ];

        return [
            'rows' => [
                'data' => OrderOperationPresenter::applyDuplicatePhoneFlags(
                    collect($paginator->items())
                        ->map(fn ($order) => OrderOperationPresenter::toArray($order, $this->configuration, $viewer))
                        ->values()
                        ->all(),
                    collect($paginator->items()),
                ),
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
