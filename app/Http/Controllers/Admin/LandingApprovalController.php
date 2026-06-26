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
            ->map(fn (MarketingSource $c) => $this->presentForApproval($c, $landing))
            ->values();

        $highlightId = $request->integer('campaign') ?: null;

        return Inertia::render('Admin/Landing/Approvals', [
            'campaigns' => $campaigns,
            'highlightCampaignId' => $highlightId,
            'fieldMapping' => $this->fieldMappingGuide(),
        ]);
    }

    public function approve(MarketingSource $campaign): RedirectResponse
    {
        abort_unless($campaign->parent_id === null, 404);

        $campaign->update(['is_approved' => true]);
        NotificationService::notifyLandingApproved($campaign->fresh());

        return back()->with('success', __('messages.landing_approved'));
    }

    /** @return array<string, mixed> */
    private function presentForApproval(MarketingSource $c, CampaignLandingService $landing): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'product' => $c->product?->name,
            'product_sku' => $c->product?->sku,
            'product_unit_price' => (int) ($c->product?->unit_price ?? 0),
            'marketer' => $c->marketer?->name,
            'creator' => $c->creator?->name,
            'ad_channel' => $c->ad_channel,
            'utm_source' => $c->utm_source,
            'utm_campaign' => $c->utm_campaign,
            'webhook_url' => $c->webhook_token ? $landing->webhookUrl($c) : null,
            'budget' => (int) $c->budget,
            'is_approved' => (bool) $c->is_approved,
            'is_active' => (bool) $c->is_active,
            'created_at' => $c->created_at?->format('d/m/Y H:i'),
        ];
    }

    /** @return list<array{ladipage: string, system: string}> */
    private function fieldMappingGuide(): array
    {
        return [
            ['ladipage' => 'name', 'system' => 'name'],
            ['ladipage' => 'phone', 'system' => 'phone'],
            ['ladipage' => 'message', 'system' => 'message'],
            ['ladipage' => 'products', 'system' => 'products'],
            ['ladipage' => 'quantity', 'system' => 'quantity'],
            ['ladipage' => 'utm_source', 'system' => 'utm_source (tùy chọn)'],
            ['ladipage' => 'utm_campaign', 'system' => 'utm_campaign (tự điền theo tên chiến dịch)'],
        ];
    }
}
