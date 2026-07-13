<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Operations\SaleOperationNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleOperationNoteController extends Controller
{
    public function update(Request $request, Order $order, SaleOperationNoteService $service): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $service->update($order, $request->user(), $validated['note'] ?? null);

        return back()->with('success', 'Đã lưu ghi chú tác nghiệp.');
    }
}
