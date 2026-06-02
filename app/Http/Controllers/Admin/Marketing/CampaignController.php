<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CampaignRequest;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $campaigns = MarketingSource::query()
            ->whereNull('parent_id')
            ->with(['product:id,name,sku', 'marketer:id,name'])
            ->withCount('orders')
            ->withSum(['orders as revenue' => function ($q) {
                $q->whereIn('delivery_status', DeliveryStatus::revenueEligible());
            }], 'total')
            ->latest('id')
            ->get()
            ->map(fn (MarketingSource $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'product' => $c->product?->name,
                'marketer' => $c->marketer?->name,
                'ad_channel' => $c->ad_channel,
                'utm_source' => $c->utm_source,
                'utm_campaign' => $c->utm_campaign,
                'budget' => (int) $c->budget,
                'is_active' => (bool) $c->is_active,
                'orders_count' => (int) $c->orders_count,
                'revenue' => (int) ($c->revenue ?? 0),
            ])
            ->values();

        return Inertia::render('Admin/Marketing/Campaigns/Index', [
            'baseUrl' => $this->baseUrl($request),
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Marketing/Campaigns/Form', [
            'baseUrl' => $this->baseUrl($request),
            'campaign' => null,
            'products' => $this->productOptions(),
            'marketers' => $this->marketerOptions(),
        ]);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        MarketingSource::query()->create($request->validated());

        return redirect($this->baseUrl($request))->with('success', 'Đã tạo chiến dịch marketing.');
    }

    public function edit(Request $request, MarketingSource $campaign): Response
    {
        return Inertia::render('Admin/Marketing/Campaigns/Form', [
            'baseUrl' => $this->baseUrl($request),
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'product_id' => $campaign->product_id,
                'marketer_user_id' => $campaign->marketer_user_id,
                'ad_channel' => $campaign->ad_channel,
                'utm_source' => $campaign->utm_source,
                'utm_campaign' => $campaign->utm_campaign,
                'budget' => (int) $campaign->budget,
                'is_active' => (bool) $campaign->is_active,
            ],
            'products' => $this->productOptions(),
            'marketers' => $this->marketerOptions(),
        ]);
    }

    public function update(CampaignRequest $request, MarketingSource $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        return redirect($this->baseUrl($request))->with('success', 'Đã cập nhật chiến dịch.');
    }

    public function destroy(Request $request, MarketingSource $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect($this->baseUrl($request))->with('success', 'Đã xóa chiến dịch.');
    }

    private function baseUrl(Request $request): string
    {
        return $request->user()?->role === UserRole::Admin
            ? '/admin/marketing/campaigns'
            : '/marketing/campaigns';
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
}
