<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Operations\FailedOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FailedOrdersController extends Controller
{
    public function __invoke(Request $request, FailedOrderService $service): Response
    {
        return Inertia::render('Admin/Orders/FailedOrders', [
            'report' => $service->build($request),
            'filterOptions' => app(FilterOptionsService::class)->forReports(),
        ]);
    }
}
