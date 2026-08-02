<?php

namespace App\Http\Controllers\Sales;

use App\Enums\OperationResult;
use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Operations\SaleOperationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleOperationStatusController extends Controller
{
    use AssertsOrderInteractionLock;

    public function update(Request $request, Order $order, SaleOperationStatusService $service): RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'operation_status');
        $validated = $request->validate([
            'operation_result' => [
                'required',
                'string',
                Rule::in(array_merge(
                    collect(OperationResult::cases())->map(fn (OperationResult $r) => $r->value)->all(),
                    ['no_answer_auto']
                )),
            ],
            'next_operation_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'confirm_insufficient_stock' => ['nullable', 'boolean'],
        ]);

        $service->applyStatus($order, $request->user(), $validated);

        return back()->with('success', __('messages.operation_status_updated'));
    }
}
