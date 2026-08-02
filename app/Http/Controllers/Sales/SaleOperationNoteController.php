<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Operations\SaleOperationNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleOperationNoteController extends Controller
{
    use AssertsOrderInteractionLock;

    public function update(Request $request, Order $order, SaleOperationNoteService $service): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'operation_note');
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $service->update($order, $request->user(), $validated['note'] ?? null);

        return back()->with('success', 'Đã lưu ghi chú tác nghiệp.');
    }
}
