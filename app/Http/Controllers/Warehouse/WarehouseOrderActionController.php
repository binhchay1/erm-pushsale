<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Warehouse\WarehouseOrderActionService;
use App\Support\ShippingProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseOrderActionController extends Controller
{
    public function __construct(private readonly WarehouseOrderActionService $service) {}

    public function desiredDelivery(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['desired_delivery_at' => ['nullable', 'date']]);
        return response()->json(['order' => $this->service->updateDesiredDelivery($order, $data['desired_delivery_at'] ?? null, $request->user())]);
    }

    public function blacklist(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->service->updateBlacklist($order, $data['phone'], $data['reason'], $request->user());
        return response()->json(['message' => 'Đã cập nhật số blacklist.']);
    }

    public function care(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['waiting', 'calling', 'confirmed', 'reschedule', 'complaint', 'completed'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        return response()->json(['order' => $this->service->updateCare($order, $data['status'] ?? null, $data['note'] ?? null, $request->user())]);
    }

    public function deliveryStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'delivery_status' => ['required', Rule::enum(DeliveryStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'collected_amount' => ['nullable', 'integer', 'min:0'],
        ]);
        return response()->json(['order' => $this->service->updateDeliveryStatus($order, $data['delivery_status'], $data['note'] ?? null, $data['collected_amount'] ?? null, $request->user())]);
    }

    public function updateOrder(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'shipping_address_2' => ['nullable', 'string', 'max:500'],
            'shipping_notes' => ['nullable', 'string', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'shipping_provider' => ['nullable', Rule::in(array_keys(config('shipping_partners.providers', [])))],
            'shipping_method' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'vat' => ['nullable', 'integer', 'min:0'],
            'shipping_fee_collected' => ['nullable', 'integer', 'min:0'],
            'deposit' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.item_type' => ['nullable', Rule::in(['product', 'combo', 'upsell', 'gift'])],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
        ]);
        return response()->json(['order' => $this->service->updateOrder($order, $data, $request->user())]);
    }

    public function split(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);
        return response()->json(['message' => 'Đã tách đơn.', 'order' => $this->service->split($order, $data['items'], $request->user())]);
    }

    public function printed(Request $request, Order $order): JsonResponse
    {
        return response()->json(['order' => $this->service->markPrinted($order, $request->user())]);
    }

    public function receiveReturn(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.order_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.received_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.restock_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.missing_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', Rule::in(['sellable', 'damaged', 'missing', 'inspection'])],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->receiveReturn($order, $data['reason'] ?? null, $data['note'] ?? null, $data['items'] ?? null, $request->user());
        return response()->json(['message' => 'Đã ghi nhận nhập hàng hoàn và cập nhật tồn kho.']);
    }
}
