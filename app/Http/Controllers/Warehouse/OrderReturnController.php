<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Inventory\InventoryReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function store(Request $request, Order $order, InventoryReturnService $service): JsonResponse
    {
        abort_unless($order->closed_at, 422, 'Đơn chưa chốt.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $service->receiveReturn($order, $data['reason'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Đã ghi nhận đơn hoàn và cập nhật tồn kho.',
        ]);
    }
}
