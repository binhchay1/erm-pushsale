<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;

/** Cùng nghiệp vụ đơn kho — tái sử dụng controller Kế toán (DRY). */
class OperationsController extends AccountingOperationsController
{
    public function __invoke(\Illuminate\Http\Request $request, \App\Services\Operations\AccountingOperationService $service): \Inertia\Response
    {
        $filter = $this->reportFilters($request);

        return \Inertia\Inertia::render('Admin/Warehouse/Operations', $this->reportPageProps($request, [
            'report' => $service->build($filter),
            'pageTitle' => 'Thủ kho tác nghiệp',
        ]));
    }
}
