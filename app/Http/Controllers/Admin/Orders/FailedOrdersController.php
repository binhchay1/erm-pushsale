<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Reporting\ReportSnapshotStore;
use App\Services\Operations\FailedOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FailedOrdersController extends Controller
{
    public function __invoke(Request $request, FailedOrderService $service): Response
    {
        $from = $request->input('date_from', now()->toDateString());
        $to = $request->input('date_to', now()->toDateString());
        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'failed-orders',
            $request->user(),
            $request->query(),
            $from,
            $to,
            'data_arrival',
            fn () => $service->build($request),
            $request->boolean('refresh'),
        );
        return Inertia::render('Admin/Orders/FailedOrders', [
            'report' => $snapshot['data'],
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
            'filterOptions' => app(FilterOptionsService::class)->forReports($request->user()),
        ]);
    }
}
