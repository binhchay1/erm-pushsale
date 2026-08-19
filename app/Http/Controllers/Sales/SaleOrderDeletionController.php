<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DataDeletion\OrderDeletionService;
use App\Services\Operations\SaleOperationPolicy;
use App\Services\Operations\SalesVisibilityScope;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleOrderDeletionController extends Controller
{
    use AssertsOrderInteractionLock;

    public function destroy(Request $request, Order $order, OrderDeletionService $deletion, SalesVisibilityScope $visibility): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'destroy');
        $actor = $request->user();

        if ($actor && ! SaleOperationPolicy::canDeleteData($order, $actor)) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.no_permission_delete'),
            ]);
        }

        if ($actor && ! $visibility->canOperateOrder($actor, $order)) {
            throw ValidationException::withMessages([
                'order' => 'Bạn không có quyền xóa data của sale khác.',
            ]);
        }

        $label = $order->order_code ?: '#'.$order->id;

        ActivityLogger::log(
            'sale_order_deleted',
            $order,
            [
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'sale_user_id' => $order->sale_user_id,
            ],
            $label,
            $actor,
        );

        $deletion->delete($order);

        return back()->with('success', 'Đã xóa data '.$label.'.');
    }
}
