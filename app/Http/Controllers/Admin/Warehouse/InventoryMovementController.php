<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Nhập / xuất kho thủ công từ màn Tồn kho sản phẩm.
 */
class InventoryMovementController extends Controller
{
    public function intake(Request $request, InventoryIntakeService $service): RedirectResponse
    {
        $data = $this->validatedMovement($request);

        $service->intake(
            $data['warehouse_id'],
            $data['product_id'],
            $data['quantity'],
            $request->user(),
            $data['note'] ?? null,
            $data['approved_by_user_id'],
        );

        return back()->with('success', __('messages.inventory_intake'));
    }

    public function export(Request $request, InventoryIntakeService $service): RedirectResponse
    {
        $data = $this->validatedMovement($request);

        $service->export(
            $data['warehouse_id'],
            $data['product_id'],
            $data['quantity'],
            $request->user(),
            $data['note'] ?? null,
            $data['approved_by_user_id'],
        );

        return back()->with('success', __('messages.inventory_export'));
    }

    /** @return array<string, mixed> */
    private function validatedMovement(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
            'approved_by_user_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'approved_by_user_id.required' => __('messages.inventory.approver_required'),
        ]);
    }
}
