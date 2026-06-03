<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Services\Marketing\CampaignLandingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LandingApprovalController extends Controller
{
    public function index(CampaignLandingService $landing): Response
    {
        $campaigns = MarketingSource::query()
            ->whereNull('parent_id')
            ->with(['product:id,name', 'marketer:id,name', 'creator:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (MarketingSource $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'product' => $c->product?->name,
                'marketer' => $c->marketer?->name,
                'creator' => $c->creator?->name,
                'utm_campaign' => $c->utm_campaign,
                'webhook_url' => $c->webhook_token ? $landing->webhookUrl($c) : null,
                'is_approved' => (bool) $c->is_approved,
                'is_active' => (bool) $c->is_active,
                'created_at' => $c->created_at?->format('d/m/Y H:i'),
            ])
            ->values();

        return Inertia::render('Admin/Landing/Approvals', [
            'campaigns' => $campaigns,
        ]);
    }

    public function approve(MarketingSource $campaign): RedirectResponse
    {
        abort_unless($campaign->parent_id === null, 404);

        $campaign->update(['is_approved' => true]);

        return back()->with('success', 'Đã duyệt nguồn Landing — lead mới sẽ được chia số cho Sale.');
    }
}
