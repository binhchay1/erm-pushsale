<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Concerns\InteractsWithReportSnapshots;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Operations\WarehouseOperationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    use InteractsWithReportFilters;
    use InteractsWithReportSnapshots;

    public function __invoke(Request $request, WarehouseOperationService $service): Response
    {
        $filter = $this->reportFilters($request);
        $snapshot = $this->maybeCachedReport(
            $request,
            'warehouse-operations',
            $filter,
            fn () => $service->build($filter),
        );
        $isWarehouseRole = $request->routeIs('warehouse.*');

        return Inertia::render('Admin/Warehouse/Operations', $this->reportPageProps($request, [
            'report' => $snapshot['data'],
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
            'routeUrl' => $isWarehouseRole ? '/warehouse/workspace' : '/admin/warehouse/operations',
            'shippingApiBase' => $isWarehouseRole ? '/warehouse/shipping/orders' : '/admin/shipping/orders',
            'actionApiBase' => $isWarehouseRole ? '/warehouse/orders' : '/admin/warehouse/orders',
            // Admin: cho xóa data chưa giao vận (icon trash góc cột Sale, giống Sale tác nghiệp).
            // Role kho: vẫn truyền true để UI hiện khi đơn đủ điều kiện; backend chặn nếu đã chốt/có vận đơn.
            'canDeleteOrder' => true,
            'filterFields' => app(FilterOptionsService::class)->warehouseOperationFilterFields(),
        ]));
    }
}
