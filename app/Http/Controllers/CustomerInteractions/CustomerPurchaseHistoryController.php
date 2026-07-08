<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Enums\ClosingStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Services\CustomerInteractions\CustomerPurchaseHistoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPurchaseHistoryController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $phoneKey = CustomerIdentity::phoneKey($order);
        $lastFourDigits = substr($phoneKey, -4);

        // Lấy tập ứng viên nhỏ bằng 4 số cuối rồi chuẩn hóa lại ở PHP để hỗ trợ
        // các định dạng 0912..., +84..., có khoảng trắng hoặc dấu chấm.
        $orders = Order::query()
            ->with([
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
                'saleUser:id,name',
                'team:id,name',
                'marketingSource:id,name',
                'warehouse:id,name',
            ])
            ->where('customer_phone', 'like', '%'.$lastFourDigits.'%')
            ->latest('data_arrived_at')
            ->latest('id')
            ->limit(1000)
            ->get()
            ->filter(fn (Order $candidate) => CustomerIdentity::samePhone($candidate->customer_phone, $phoneKey))
            ->take(100)
            ->values();

        $closedOrders = $orders->filter(fn (Order $item) => $item->closed_at !== null
            || $item->closing_status === ClosingStatus::Closed->value);

        return response()->json([
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->effectiveShippingAddress(),
                'selectedOrderCode' => $order->order_code,
            ],
            'summary' => [
                'orderCount' => $orders->count(),
                'closedOrderCount' => $closedOrders->count(),
                'totalQuantity' => (int) $orders->sum(fn (Order $item) => $item->items->sum('quantity')),
                'totalValue' => (int) $closedOrders->sum(fn (Order $item) => $item->effectiveRevenue()),
                'firstOrderAt' => $orders->sortBy('data_arrived_at')->first()?->data_arrived_at?->toIso8601String(),
                'latestOrderAt' => $orders->first()?->data_arrived_at?->toIso8601String(),
            ],
            'orders' => CustomerPurchaseHistoryPresenter::collection($orders, $order->id),
            'limited' => $orders->count() >= 100,
        ]);
    }
}
