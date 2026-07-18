<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ClosingStatus;
use App\Enums\DateType;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FilterOptionsService;
use App\Services\Operations\SaleOperationConfigurationService;
use App\Services\Operations\SaleOperationService;
use App\Support\ShippingProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(
        Request $request,
        SaleOperationService $service,
        FilterOptionsService $filterOptions,
        SaleOperationConfigurationService $configuration,
    ): Response {
        $filter = $this->reportFilters($request);
        $options = $filterOptions->forReports($request->user());
        $options['dateTypes'] = collect(DateType::cases())->map(fn (DateType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->values()->all();
        $options['teamLeaders'] = $this->teamLeaderOptions();
        $options['teams'] = $this->teamOptions();
        $options['salesUsers'] = $this->saleOptions($request);
        $options['marketingSources'] = $this->sourceOptions();
        $options['closingStatuses'] = ClosingStatus::options();
        $options['deliveryStatuses'] = collect(DeliveryStatus::cases())->map(fn (DeliveryStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values()->all();
        $options['operationResults'] = OperationResult::filterOptions();

        try {
            $report = $service->buildPaginated($filter);
            $workspaceError = null;
        } catch (\Throwable $e) {
            Log::error('Sales workspace failed to build report', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'filters' => $request->query(),
            ]);

            $report = [
                'rows' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $filter->perPage, 'total' => 0, 'from' => null, 'to' => null]],
                'statusTabs' => [],
            ];
            $workspaceError = app()->isProduction()
                ? 'Không tải được dữ liệu tác nghiệp Sale. Vui lòng xem log staging để xử lý.'
                : $e->getMessage();
        }

        return Inertia::render('Sales/Workspace', array_merge(
            $this->reportPageProps($request, [
                'report' => $report,
            ]),
            [
                'workspaceError' => $workspaceError,
                'activeMenuCode' => '4.1',
                'filterOptions' => $options,
                'filterFields' => [
                    'team_leader_id', 'team_id', 'sale_id', 'search',
                    'date_type', 'date_from', 'date_to', 'care_status', 'marketing_source_id',
                    'product_id', 'operation_activity_status', 'operation_result', 'closing_status', 'delivery_status',
                    'hide_zero_status', 'per_page',
                ],
                'operationStageOptions' => $configuration->definitions(),
                'operationStatusOptions' => OperationResult::selectableOptions(),
                'carrierOptions' => ShippingProviders::options(),
                'shippingServiceOptions' => ShippingProviders::serviceOptions(),
                'itemTypeOptions' => ['product', 'combo', 'upsell', 'gift'],
                'warehouseOptions' => $this->warehouseOptions(),
                'productOptions' => $this->productOptions(),
                'sourceOptions' => $this->sourceOptions(),
                'routeUrl' => $this->workspaceRouteUrl($request),
                'actionBaseUrl' => $this->workspaceActionBaseUrl($request),
                'manualUrl' => $this->workspaceManualUrl($request),
            ]
        ));
    }

    protected function workspaceRouteUrl(Request $request): string
    {
        return '/sales/workspace';
    }

    protected function workspaceActionBaseUrl(Request $request): string
    {
        return '/sales';
    }

    protected function workspaceManualUrl(Request $request): string
    {
        return '/sales/leads/manual';
    }

    /** @return list<array{id:int,name:string}> */
    private function teamLeaderOptions(): array
    {
        return User::query()
            ->where(function ($query): void {
                $query->where('is_team_leader', true)
                    ->orWhereIn('id', Team::query()->whereNotNull('leader_user_id')->select('leader_user_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => $user->name])
            ->all();
    }

    /** @return list<array{id:int,name:string}> */
    private function teamOptions(): array
    {
        return Team::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Team $team) => ['id' => (int) $team->id, 'name' => $team->name])
            ->all();
    }

    /** @return list<array{id:int,name:string}> */
    private function saleOptions(Request $request): array
    {
        $query = User::query()->where('role', User::ROLE_SALES)->orderBy('name');
        if ($request->user()?->isSales()) {
            $query->whereKey($request->user()->id);
        }

        return $query->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => $user->name])
            ->all();
    }

    /** @return list<array{id:int,name:string}> */
    private function sourceOptions(): array
    {
        return MarketingSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MarketingSource $source) => ['id' => (int) $source->id, 'name' => $source->name])
            ->all();
    }

    /** @return list<array{id:int,name:string}> */
    private function warehouseOptions(): array
    {
        return Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'pick_province', 'pick_district', 'pick_ward'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => (int) $warehouse->id,
                'name' => $warehouse->name,
                'address' => $warehouse->address,
                'pickup_address' => collect([$warehouse->address, $warehouse->pick_ward, $warehouse->pick_district, $warehouse->pick_province])->filter()->implode(', '),
            ])
            ->all();
    }

    /** @return list<array{id:int,name:string,type:string,sku:?string,unit_price:int}> */
    private function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'sku', 'unit_price'])
            ->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'type' => $product->type ?? 'product',
                'sku' => $product->sku,
                'unit_price' => (int) $product->unit_price,
            ])
            ->all();
    }
}
