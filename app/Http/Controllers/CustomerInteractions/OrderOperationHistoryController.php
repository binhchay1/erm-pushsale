<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerInteractions\OrderOperationHistoryPresenter;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Models\OrderOperationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderOperationHistoryController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        if ($request->boolean('same_phone')) {
            $phoneKey = CustomerIdentity::phoneKey($order);
            $lastFourDigits = substr($phoneKey, -4);
            $orderIds = Order::query()
                ->where('customer_phone', 'like', '%'.$lastFourDigits.'%')
                ->get(['id', 'customer_phone'])
                ->filter(fn (Order $candidate) => CustomerIdentity::samePhone($candidate->customer_phone, $phoneKey))
                ->pluck('id');

            $histories = OrderOperationHistory::query()
                ->whereIn('order_id', $orderIds)
                ->with(['actor:id,name,role,org_level', 'order:id,order_code'])
                ->latest('created_at')
                ->latest('id')
                ->limit(201)
                ->get();
        } else {
            $histories = $order->operationHistories()
                ->with(['actor:id,name,role,org_level', 'order:id,order_code'])
                ->latest('created_at')
                ->latest('id')
                ->limit(201)
                ->get();
        }

        $hasMore = $histories->count() > 200;
        $histories = $histories->take(200);
        $items = OrderOperationHistoryPresenter::collection($histories);

        if ($items === []) {
            $items[] = OrderOperationHistoryPresenter::currentSnapshot($order);
        }

        return response()->json([
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'orderCode' => $order->order_code,
            ],
            'histories' => $items,
            'hasMore' => $hasMore,
        ]);
    }
}
