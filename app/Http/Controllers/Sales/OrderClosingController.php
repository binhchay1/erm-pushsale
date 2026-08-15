<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderClosingController extends Controller
{
    use AssertsOrderInteractionLock;

    public function store(Request $request, Order $order, OrderClosingService $service): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'close');
        $validated = $request->validate([
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'shipping_geo' => ['nullable', 'array'],
            'shipping_geo.province' => ['nullable', 'string', 'max:120'],
            'shipping_geo.district' => ['nullable', 'string', 'max:120'],
            'shipping_geo.ward' => ['nullable', 'string', 'max:120'],
            'shipping_geo.hamlet' => ['nullable', 'string', 'max:120'],
            'shipping_geo.address' => ['nullable', 'string', 'max:500'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'shipping_method' => ['nullable', 'string', 'max:40'],
            'shipping_provider' => ['nullable', 'string', 'in:ghtk,ghn,viettel_post,jnt'],
            'amount_to_collect' => ['nullable', 'integer', 'min:0'],
            'confirm_insufficient_stock' => ['nullable', 'boolean'],
        ]);

        $service->close($order, $request->user(), $validated);

        return back()->with('success', __('messages.order_closed'));
    }

    public function unclose(Request $request, Order $order, OrderClosingService $service): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'unclose');
        $service->unclose($order, $request->user());

        return back()->with('success', __('messages.sale_ops.unclose_success'));
    }
}
