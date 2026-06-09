<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Operations\WarehouseOperationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, WarehouseOperationService $service): Response
    {
        $filter = $this->reportFilters($request);
        $isWarehouseRole = $request->routeIs('warehouse.*');

        return Inertia::render('Admin/Warehouse/Operations', $this->reportPageProps($request, [
            'report' => $service->build($filter),
            'pageTitle' => 'Xuất kho & vận đơn',
            'routeUrl' => $isWarehouseRole ? '/warehouse/workspace' : '/admin/warehouse/operations',
            'shippingApiBase' => $isWarehouseRole ? '/warehouse/shipping/orders' : '/admin/shipping/orders',
            'canDeleteOrder' => ! $isWarehouseRole,
            'filterFields' => app(FilterOptionsService::class)->warehouseOperationFilterFields(),
        ]));
    }
}
