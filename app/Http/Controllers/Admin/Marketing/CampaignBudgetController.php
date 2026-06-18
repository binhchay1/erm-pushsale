<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignBudgetController extends Controller
{
    public function update(Request $request, MarketingSource $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $validated = $request->validate([
            'budget' => ['required', 'integer', 'min:0'],
        ]);

        $campaign->update(['budget' => $validated['budget']]);

        return back()->with('success', __('messages.campaign_budget_updated'));
    }

    private function authorizeCampaign(Request $request, MarketingSource $campaign): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->role === UserRole::Marketing && (int) $campaign->marketer_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }
}
