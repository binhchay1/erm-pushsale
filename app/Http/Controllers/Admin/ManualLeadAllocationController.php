<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        return back()->with('success', "Đã phân bổ {$count} lead cho {$saleUser->name}.");
    }
}
