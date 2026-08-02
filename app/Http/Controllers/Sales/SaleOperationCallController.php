<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Operations\SaleOperationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleOperationCallController extends Controller
{
    use AssertsOrderInteractionLock;

    public function store(Request $request, Order $order, SaleOperationStatusService $service): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'call');
        $service->logCall($order, $request->user());

        return back()->with('success', __('messages.call_logged'));
    }
}
