<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryIntakeController extends Controller
{
    public function store(Request $request, InventoryIntakeService $service): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $service->intake(
            $validated['warehouse_id'],
            $validated['product_id'],
            $validated['quantity'],
            $request->user(),
            $validated['note'] ?? null,
        );

        return back()->with('success', 'Đã nhập kho thành công.');
    }
}
