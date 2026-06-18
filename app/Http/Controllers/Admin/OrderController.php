<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DataDeletion\OrderDeletionService;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    public function destroy(Order $order, OrderDeletionService $deletion): RedirectResponse
    {
        $label = $order->order_code;
        $deletion->delete($order);

        return back()->with('success', __('messages.order_deleted', ['label' => $label]));
    }
}
