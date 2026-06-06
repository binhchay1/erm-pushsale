<?php

namespace App\Http\Controllers\Sales;

use App\Enums\OperationResult;
use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Operations\SaleOperationService;
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
            ]
        ));
    }
}
