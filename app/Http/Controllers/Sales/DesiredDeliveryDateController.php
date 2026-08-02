<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Operations\SalesVisibilityScope;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesiredDeliveryDateController extends Controller
{
    use AssertsOrderInteractionLock;

    public function update(Request $request, Order $order, OrderOperationHistoryService $history, SalesVisibilityScope $visibility): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'desired_delivery');
        if (! $visibility->canOperateOrder($request->user(), $order)) {
            throw ValidationException::withMessages(['order' => __('messages.sale_ops.no_permission_operate')]);
        }

        $validated = $request->validate([
            'desired_delivery_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($order, $request, $history, $validated): void {
            $before = $history->snapshot($order);
            $order->update(['desired_delivery_at' => Carbon::parse($validated['desired_delivery_at'])]);
            $fresh = $order->fresh();

            ActivityLogger::log(
                ActivityLogger::ORDER_UPDATED,
                $fresh,
                ['changed_fields' => ['desired_delivery_at']],
                $fresh->order_code ?? ('#'.$fresh->id),
                $request->user(),
            );

            $history->record(
                $fresh,
                $request->user(),
                OrderOperationHistory::ACTION_DESIRED_DELIVERY_UPDATED,
                $before,
                $history->snapshot($fresh),
                metadata: ['desired_delivery_at' => $fresh->desired_delivery_at?->toIso8601String()],
            );
        });

        return back()->with('success', 'Đã cập nhật ngày muốn nhận hàng.');
    }
}
