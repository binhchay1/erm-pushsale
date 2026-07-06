<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\CampaignLeadAllocation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignRequest;
use App\Models\MarketingSource;
use App\Repositories\MarketingSourceRepository;
use App\Repositories\UserRepository;
use App\Services\Marketing\CampaignJsSnippetService;
use App\Services\Marketing\CampaignLandingService;
use App\Services\NotificationService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignLandingService $landing,
        private readonly CampaignJsSnippetService $jsSnippet,
        private readonly MarketingSourceRepository $sources,
        private readonly UserRepository $users,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);

        $ownership = $request->input('ownership', 'all');
        if (! in_array($ownership, ['all', 'created', 'delegated'], true)) {
            $ownership = 'all';
        }

        $user = $request->user();
        $campaigns = $this->sources->visibleCampaignsWithStats($user, $ownership)
            ->map(fn (MarketingSource $c) => $this->presentCampaign($c, $user))
            ->values();

        return Inertia::render('Marketing/Campaigns/Index', [
            'campaigns' => $campaigns,
            'ownershipFilter' => $ownership,
            'currentUserId' => $user->id,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);

        return Inertia::render('Marketing/Campaigns/Form', [
            'campaign' => null,
            'marketers' => $this->marketerOptions(),
            'fieldMapping' => $this->fieldMappingGuide(),
            'allocationOptions' => $this->allocationOptions(),
        ]);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $data = $this->landing->prepareForCreate($request->validated(), $request->user()->id);
        $campaign = MarketingSource::query()->create($data);
        NotificationService::notifyLandingApprovalPending($campaign);

        ActivityLogger::log(
            ActivityLogger::CAMPAIGN_CREATED,
            $campaign,
            [
                'marketer_user_id' => $campaign->marketer_user_id,
            ],
        );

        return redirect()->route('marketing.campaigns.index')
            ->with('success', __('messages.campaign_created'));
    }

    public function edit(Request $request, MarketingSource $campaign): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);
        $this->authorizeCampaignOwner($request, $campaign);

        return Inertia::render('Marketing/Campaigns/Form', [
            'campaign' => $this->presentCampaign($campaign, $request->user(), includeEdit: true),
            'marketers' => $this->marketerOptions(),
            'fieldMapping' => $this->fieldMappingGuide(),
            'allocationOptions' => $this->allocationOptions(),
        ]);
    }

    public function update(CampaignRequest $request, MarketingSource $campaign): RedirectResponse
    {
        $this->authorizeCampaignOwner($request, $campaign);
        $data = $this->landing->prepareForUpdate($campaign, $request->validated());
        $campaign->update($data);

        ActivityLogger::log(
            ActivityLogger::CAMPAIGN_UPDATED,
            $campaign->fresh(),
            [
                'marketer_user_id' => $campaign->marketer_user_id,
            ],
        );

        return redirect()->route('marketing.campaigns.index')->with('success', __('messages.campaign_updated'));
    }

    public function destroy(Request $request, MarketingSource $campaign): RedirectResponse
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);
        $this->authorizeCampaignOwner($request, $campaign);

        ActivityLogger::log(ActivityLogger::CAMPAIGN_DELETED, $campaign, [], $campaign->name);

        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')->with('success', __('messages.campaign_deleted'));
    }

    /** @return array<string, mixed> */
    private function presentCampaign(MarketingSource $c, \App\Models\User $viewer, bool $includeEdit = false): array
    {
        $isOwner = (int) $c->created_by_user_id === (int) $viewer->id;
        $isDelegated = (int) $c->marketer_user_id === (int) $viewer->id && ! $isOwner;

        $base = [
            'id' => $c->id,
            'name' => $c->name,
            'creator' => $c->creator?->name,
            'created_by_user_id' => $c->created_by_user_id,
            'marketer' => $c->marketer?->name,
            'marketer_user_id' => $c->marketer_user_id,
            'approver' => $c->approver?->name,
            'approved_at' => $c->approved_at?->format('d/m/Y H:i'),
            'ad_channel' => $c->ad_channel,
            'utm_campaign' => $c->utm_campaign,
            'webhook_url' => $c->webhook_token ? $this->landing->webhookUrl($c) : null,
            'budget' => (int) $c->budget,
            'is_active' => (bool) $c->is_active,
            'is_approved' => (bool) $c->is_approved,
            'js_tracking_enabled' => (bool) $c->js_tracking_enabled,
            'lead_allocation' => $c->lead_allocation?->value ?? 'inherit',
            'orders_count' => (int) ($c->orders_count ?? 0),
            'revenue' => (int) ($c->revenue ?? 0),
            'ownership' => $isOwner ? 'created' : ($isDelegated ? 'delegated' : 'team'),
            'can_edit' => $isOwner,
            'can_delete' => $isOwner,
        ];

        if ($includeEdit) {
            $base['lead_allocation'] = $c->lead_allocation?->value ?? 'inherit';
            $base['js_snippet'] = $c->webhook_token && $c->js_tracking_enabled
                ? $this->jsSnippet->render($c)
                : null;
        }

        return $base;
    }

    private function authorizeCampaignOwner(Request $request, MarketingSource $campaign): void
    {
        abort_unless(
            $campaign->parent_id === null && $campaign->created_by_user_id === $request->user()->id,
            403,
        );
    }

    /** @return list<array{id:int,name:string}> */
    private function marketerOptions(): array
    {
        return $this->users->nameOptionsByRoles([UserRole::Marketing]);
    }

    /** @return list<array{value: string, label: string}> */
    private function allocationOptions(): array
    {
        return collect(CampaignLeadAllocation::cases())
            ->map(fn (CampaignLeadAllocation $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{key: string, api_name: string, required: bool}> */
    private function fieldMappingGuide(): array
    {
        return [
            ['key' => 'name', 'api_name' => 'name', 'required' => false],
            ['key' => 'phone', 'api_name' => 'phone', 'required' => true],
            ['key' => 'address', 'api_name' => 'address', 'required' => false],
            ['key' => 'products', 'api_name' => 'products', 'required' => false],
            ['key' => 'quantity', 'api_name' => 'quantity', 'required' => false],
            ['key' => 'combo', 'api_name' => 'combo', 'required' => false],
            ['key' => 'discount', 'api_name' => 'discount', 'required' => false],
            ['key' => 'message', 'api_name' => 'message', 'required' => false],
        ];
    }
}
