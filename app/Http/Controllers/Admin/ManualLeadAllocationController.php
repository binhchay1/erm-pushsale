<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadAllocationMode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\ManualLeadAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManualLeadAllocationController extends Controller
{
    public function store(Request $request, ManualLeadAllocationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:lead_ingestions,id'],
            'sale_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_SALES),
            ],
        ]);

        $saleUser = User::query()->findOrFail($validated['sale_user_id']);
        $count = $service->allocate($validated['lead_ids'], $saleUser, $request->user());

        return back()->with('success', __('messages.leads_allocated', ['count' => $count, 'name' => $saleUser->name]));
    }

    public function updateMode(Request $request, LeadAllocationModeService $modeService): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::enum(LeadAllocationMode::class)],
        ]);

        $mode = LeadAllocationMode::from($validated['mode']);
        $modeService->set($mode);

        $key = $mode->isAuto() ? 'messages.lead_allocation.mode_auto_on' : 'messages.lead_allocation.mode_manual_on';

        return back()->with('success', __($key));
    }
}
