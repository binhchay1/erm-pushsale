<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignRequest;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Marketing\CampaignLandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignLandingService $landing) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);

        $campaigns = MarketingSource::query()
            ->whereNull('parent_id')
            ->where('created_by_user_id', $request->user()->id)
            ->with(['product:id,name,sku', 'marketer:id,name'])
            ->withCount('orders')
            ->withSum(['orders as revenue' => function ($q) {
                $q->whereIn('delivery_status', DeliveryStatus::revenueEligible());
            }], 'total')
            ->latest('id')
            ->get()
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
        MarketingSource::query()->create($data);

        return redirect()->route('marketing.campaigns.index')
            ->with('success', 'Đã tạo kết nối Landing — copy URL API sang Ladipage và chờ Admin duyệt.');
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

        return redirect()->route('marketing.campaigns.index')->with('success', 'Đã cập nhật kết nối Landing.');
    }

    public function destroy(Request $request, MarketingSource $campaign): RedirectResponse
    {
        abort_unless($request->user()?->role === UserRole::Marketing, 403);
        $this->authorizeCampaignOwner($request, $campaign);
        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')->with('success', 'Đã xóa kết nối Landing.');
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
        return Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->sku ? $p->name.' ('.$p->sku.')' : $p->name,
            ])
            ->all();
    }

    /** @return list<array{id:int,name:string}> */
    private function marketerOptions(): array
    {
        return User::query()
            ->where('role', UserRole::Marketing)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
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
