<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Leads\DataDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataDistributionController extends Controller
{
    public function index(Request $request, DataDistributionService $service): Response
    {
        $payload = $service->indexPayload($request->user(), $request->query());

        return Inertia::render('Admin/DataDistribution/Index', array_merge($payload, [
            'activeMenuCode' => '1.5',
            'pageTitle' => 'Phân bổ data',
        ]));
    }

    public function store(Request $request, DataDistributionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'filters' => ['nullable', 'array'],
            'product_allocations' => ['required', 'array', 'min:1'],
            'product_allocations.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'product_allocations.*.quantity' => ['required', 'integer', 'min:0', 'max:5000'],
            'sale_user_ids' => ['required', 'array', 'min:1'],
            'sale_user_ids.*' => ['integer', 'exists:users,id'],
            'operation_policy' => ['nullable', 'string', 'max:80'],
            'delete_operation_history' => ['boolean'],
            'delete_internal_messages' => ['boolean'],
            'hide_sales_not_receiving' => ['boolean'],
            'skip_sales_not_receiving' => ['boolean'],
            'hide_locked_sales' => ['boolean'],
            'skip_locked_sales' => ['boolean'],
        ]);

        $result = $service->distribute($request->user(), $validated);

        return back()->with('success', "Đã phân bổ {$result['allocated']}/{$result['requested']} contact cho Sale.");
    }
}
