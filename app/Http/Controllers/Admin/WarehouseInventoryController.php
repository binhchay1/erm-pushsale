<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarehouseInventory;
use Illuminate\Http\RedirectResponse;

class WarehouseInventoryController extends Controller
{
    public function destroy(WarehouseInventory $inventory): RedirectResponse
    {
        $inventory->delete();

        return back()->with('success', __('messages.inventory_row_deleted'));
    }
}
