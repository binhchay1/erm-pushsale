<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Services\FilterOptionsService;
use App\Services\Reporting\ReportSnapshotStore;
use App\Services\Inventory\WarehouseInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __invoke(
        Request $request,
        WarehouseInventoryService $service,
        UserRepository $users,
    ): Response {
        $base = $request->is('warehouse/*') ? '/warehouse/inventory' : '/admin/warehouse/inventory';

        $from = $request->input('date_from', now()->toDateString());
        $to = $request->input('date_to', now()->toDateString());
        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'warehouse-inventory',
            $request->user(),
            $request->query(),
            $from,
            $to,
            'data_arrival',
            fn () => $service->build($request),
            $request->boolean('refresh'),
        );

        return Inertia::render('Admin/Warehouse/Inventory', [
            'report' => $snapshot['data'],
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
            'filterOptions' => app(FilterOptionsService::class)->forReports($request->user()),
            'intakeUrl' => $base.'/intake',
            'exportUrl' => $base.'/export',
            'approverOptions' => $users->warehouseApprovers(),
            'activeMenuCode' => '5.2.2',
        ]);
    }
}
