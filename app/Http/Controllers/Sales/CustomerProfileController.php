<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\InteractsWithReportFilters;
use App\Http\Controllers\Controller;
use App\Services\Operations\SaleOperationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    use InteractsWithReportFilters;

    public function __invoke(Request $request, SaleOperationService $service): Response
    {
        $filter = $this->reportFilters($request);

        return Inertia::render('Sales/CustomerProfile', $this->reportPageProps($request, [
            'report' => $service->buildPaginated($filter),
            'routeUrl' => '/'.$request->path(),
        ]));
    }
}
