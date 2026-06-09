<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Services\FilterOptionsService;
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

        return Inertia::render('Admin/Warehouse/Inventory', [
            'report' => $service->build($request),
            'filterOptions' => app(FilterOptionsService::class)->forReports($request->user()),
            'intakeUrl' => $base.'/intake',
            'exportUrl' => $base.'/export',
            'approverOptions' => $users->warehouseApprovers(),
        ]);
    }
}
