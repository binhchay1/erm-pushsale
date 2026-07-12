<?php

namespace App\Http\Controllers\Sales;

use App\Enums\OperationResult;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\FilterOptionsService;
use App\Services\Operations\SaleOperationService;
use App\Support\ShippingProviders;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, SaleOperationService $service, FilterOptionsService $filterOptions): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Sales/Workspace', array_merge(
            $this->reportPageProps($request, [
                'report' => $service->build($filter),
            ]),
            [
                'filterFields' => $filterOptions->saleOperationFilterFields(),
                'operationStatusOptions' => OperationResult::selectableOptions(),
                'carrierOptions' => ShippingProviders::options(),
                'shippingServiceOptions' => ShippingProviders::serviceOptions(),
                'itemTypeOptions' => ['product', 'combo', 'upsell', 'gift'],
                'warehouseOptions' => $this->warehouseOptions(),
                'productOptions' => $this->productOptions(),
                'sourceOptions' => $this->sourceOptions(),
                'routeUrl' => $request->is('admin/*') ? '/admin/sales/workspace' : '/sales/workspace',
                'actionBaseUrl' => $request->is('admin/*') ? '/admin/sales' : '/sales',
                'manualUrl' => $request->is('admin/*') ? '/admin/sales/leads/manual' : '/sales/leads/manual',
            ]
        ));
    }

    /** @return list<array{id: int, name: string}> */
    private function sourceOptions(): array
    {
        return MarketingSource::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MarketingSource $source) => ['id' => $source->id, 'name' => $source->name])
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function warehouseOptions(): array
    {
        return Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $w) => ['id' => $w->id, 'name' => $w->name])
            ->all();
    }

    /** @return list<array{id: int, name: string, type: string, sku: ?string, unit_price: int}> */
    private function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'sku', 'unit_price'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type ?? 'product',
                'sku' => $p->sku,
                'unit_price' => (int) $p->unit_price,
            ])
            ->all();
    }
}
