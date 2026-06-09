<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Http\Traits\ApiResponds;
use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponds;

    public function index(Request $request, OrderRepository $orders): JsonResponse
    {
        $list = $orders->paginatedApiList(
            $request->user()->isSales() ? $request->user()->id : null,
            $request->query('search'),
            min((int) $request->query('per_page', 20), 100),
        );

        return $this->success(OrderResource::collection($list));
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
