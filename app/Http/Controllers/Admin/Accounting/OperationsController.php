<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Operations\AccountingOperationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, AccountingOperationService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Admin/Accounting/Operations', $this->reportPageProps($request, [
            'report' => $service->build($filter),
        ]));
    }
}
