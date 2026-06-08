<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Inventory\WarehouseInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __invoke(Request $request, WarehouseInventoryService $service): Response
    {
        $intakeUrl = $request->is('warehouse/*')
            ? '/warehouse/inventory/intake'
            : '/admin/warehouse/inventory/intake';

        return Inertia::render('Admin/Warehouse/Inventory', [
            'report' => $service->build($request),
            'filterOptions' => app(FilterOptionsService::class)->forReports($request->user()),
            'intakeUrl' => $intakeUrl,
        ]);
    }
}
