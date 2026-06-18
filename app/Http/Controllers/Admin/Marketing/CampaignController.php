<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignRequest;
use App\Models\MarketingSource;
use App\Repositories\MarketingSourceRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Services\Marketing\CampaignLandingService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignLandingService $landing,
        private readonly MarketingSourceRepository $sources,
        private readonly ProductRepository $products,
        private readonly UserRepository $users,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);

        $campaigns = $this->sources->ownedCampaignsWithStats($request->user()->id)
            ->map(fn (MarketingSource $c) => $this->presentCampaign($c))
            ->values();

        return Inertia::render('Marketing/Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);

        return Inertia::render('Marketing/Campaigns/Form', [
            'campaign' => null,
            'products' => $this->productOptions(),
            'marketers' => $this->marketerOptions(),
            'fieldMapping' => $this->fieldMappingGuide(),
        ]);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $data = $this->landing->prepareForCreate($request->validated(), $request->user()->id);
        $campaign = MarketingSource::query()->create($data);
        NotificationService::notifyLandingApprovalPending($campaign);

        return redirect()->route('marketing.campaigns.index')
            ->with('success', __('messages.campaign_created'));
    }

    public function edit(Request $request, MarketingSource $campaign): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);
        $this->authorizeCampaignOwner($request, $campaign);

        return Inertia::render('Marketing/Campaigns/Form', [
            'campaign' => $this->presentCampaign($campaign, includeEdit: true),
            'products' => $this->productOptions(),
            'marketers' => $this->marketerOptions(),
            'fieldMapping' => $this->fieldMappingGuide(),
        ]);
    }

    public function update(CampaignRequest $request, MarketingSource $campaign): RedirectResponse
    {
        $this->authorizeCampaignOwner($request, $campaign);
        $data = $this->landing->prepareForUpdate($campaign, $request->validated());
        $campaign->update($data);

        return redirect()->route('marketing.campaigns.index')->with('success', __('messages.campaign_updated'));
    }

    public function destroy(Request $request, MarketingSource $campaign): RedirectResponse
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);
        $this->authorizeCampaignOwner($request, $campaign);
        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')->with('success', __('messages.campaign_deleted'));
    }

    /** @return array<string, mixed> */
    private function presentCampaign(MarketingSource $c, bool $includeEdit = false): array
    {
        $base = [
            'id' => $c->id,
            'name' => $c->name,
            'product' => $c->product?->name,
            'marketer' => $c->marketer?->name,
            'ad_channel' => $c->ad_channel,
            'utm_campaign' => $c->utm_campaign,
            'webhook_url' => $c->webhook_token ? $this->landing->webhookUrl($c) : null,
            'budget' => (int) $c->budget,
            'is_active' => (bool) $c->is_active,
            'is_approved' => (bool) $c->is_approved,
            'orders_count' => (int) ($c->orders_count ?? 0),
            'revenue' => (int) ($c->revenue ?? 0),
        ];

        if ($includeEdit) {
            $base['product_id'] = $c->product_id;
            $base['marketer_user_id'] = $c->marketer_user_id;
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
    private function productOptions(): array
    {
        return $this->products->optionsWithSkuLabel();
    }

    /** @return list<array{id:int,name:string}> */
    private function marketerOptions(): array
    {
        return $this->users->nameOptionsByRoles([UserRole::Marketing]);
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
