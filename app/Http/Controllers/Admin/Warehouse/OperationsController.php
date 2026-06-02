<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Services\Operations\AccountingOperationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Cùng nghiệp vụ đơn kho — tái sử dụng controller Kế toán (DRY). */
class OperationsController extends AccountingOperationsController
{
    public function __invoke(Request $request, AccountingOperationService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Admin/Warehouse/Operations', $this->reportPageProps($request, [
            'report' => $service->build($filter),
            'pageTitle' => 'Thủ kho tác nghiệp',
        ]));
    }
}
