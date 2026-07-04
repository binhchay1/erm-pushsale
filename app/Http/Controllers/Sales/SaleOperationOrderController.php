<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Operations\SaleOrderEditService;
use App\Support\ShippingProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleOperationOrderController extends Controller
{
    public function update(Request $request, Order $order, SaleOrderEditService $service): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.item_type' => ['nullable', Rule::in(['product', 'combo', 'upsell', 'gift'])],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'shipping_provider' => ['nullable', Rule::in(ShippingProviders::keys())],
            'shipping_service' => ['nullable', 'string', 'max:30'],
            'carrier_name' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'address_mode' => ['nullable', Rule::in(['old', 'new'])],
            'address_detail' => ['nullable', 'string', 'max:200'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'ward_code' => ['nullable', 'string', 'max:20'],
            'receiver_is_customer' => ['nullable', 'boolean'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:30'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'shipping_fee_collected' => ['nullable', 'integer', 'min:0'],
            'deposit' => ['nullable', 'integer', 'min:0'],
            'vat' => ['nullable', 'integer', 'min:0'],
        ]);

        $service->update($order, $request->user(), $validated);

        return back()->with('success', __('messages.operation_status_updated'));
    }
}
