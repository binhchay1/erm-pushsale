<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarehouseInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseInventoryController extends Controller
{
    public function updateDiscontinued(Request $request, WarehouseInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'is_discontinued' => ['required', Rule::in([0, 1, true, false, '0', '1'])],
        ]);

        $inventory->update([
            'is_discontinued' => filter_var($data['is_discontinued'], FILTER_VALIDATE_BOOLEAN),
        ]);

        return back()->with('success', __('messages.inventory_discontinued_updated'));
    }

    public function destroy(WarehouseInventory $inventory): RedirectResponse
    {
        $inventory->delete();

        return back()->with('success', __('messages.inventory_row_deleted'));
    }
}
