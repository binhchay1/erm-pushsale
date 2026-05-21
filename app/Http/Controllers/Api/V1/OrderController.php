<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Http\Traits\ApiResponds;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['saleUser', 'marketingSource'])
            ->when($request->user()->isSales(), fn ($q) => $q->where('sale_user_id', $request->user()->id))
            ->when($request->query('search'), function ($q, $search) {
                $term = '%'.$search.'%';
                $q->where(fn ($inner) => $inner
                    ->where('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhere('order_code', 'like', $term));
            })
            ->latest('data_arrived_at')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return $this->success(OrderResource::collection($orders));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($request->user()->isSales() && $order->sale_user_id !== $request->user()->id) {
            return $this->error('Không có quyền xem đơn này', 403);
        }

        $order->load(['saleUser', 'marketingSource', 'items']);

        return $this->success(new OrderResource($order));
    }
}
