<?php

namespace App\Http\Controllers\Admin\Accounting;

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
            'accounting-operations',
            $filter,
            fn () => $service->build($filter),
            useCache: false,
        );
        $isAccountingRole = $request->routeIs('accounting.*');

        return Inertia::render('Admin/Accounting/Operations', $this->reportPageProps($request, [
            'report' => $snapshot['data'],
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
            'pageTitle' => 'Kế toán tác nghiệp',
            'routeUrl' => $isAccountingRole ? '/accounting/workspace' : '/admin/accounting',
            'shippingApiBase' => $isAccountingRole ? '/accounting/shipping/orders' : '/admin/shipping/orders',
            'actionApiBase' => $isAccountingRole ? '/accounting/orders' : '/admin/warehouse/orders',
            'canDeleteOrder' => true,
            'activeMenuCode' => '6.1',
            'filterFields' => app(FilterOptionsService::class)->warehouseOperationFilterFields(),
        ]));
    }
}
