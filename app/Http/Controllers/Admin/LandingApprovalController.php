<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingSource;
use App\Repositories\MarketingSourceRepository;
use App\Services\Marketing\CampaignLandingService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingApprovalController extends Controller
{
    public function index(
        Request $request,
        CampaignLandingService $landing,
        MarketingSourceRepository $sources,
    ): Response {
        $campaigns = $sources->rootCampaignsForApproval()
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

        $highlightId = $request->integer('campaign') ?: null;

        return Inertia::render('Admin/Landing/Approvals', [
            'campaigns' => $campaigns,
            'highlightCampaignId' => $highlightId,
        ]);
    }

    public function approve(MarketingSource $campaign): RedirectResponse
    {
        abort_unless($campaign->parent_id === null, 404);

        $campaign->update(['is_approved' => true]);
        NotificationService::notifyLandingApproved($campaign->fresh());

        return back()->with('success', 'Đã duyệt nguồn Landing — lead mới sẽ được chia số cho Sale.');
    }
}
