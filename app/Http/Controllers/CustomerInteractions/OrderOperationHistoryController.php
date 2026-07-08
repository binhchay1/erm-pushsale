<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerInteractions\OrderOperationHistoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderOperationHistoryController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $histories = $order->operationHistories()
            ->with('actor:id,name,role,org_level')
            ->latest('created_at')
            ->latest('id')
            ->limit(201)
            ->get();

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
