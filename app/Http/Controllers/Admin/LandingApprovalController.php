<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectCampaignRequest;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSource;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Repositories\MarketingSourceRepository;
use App\Services\Marketing\CampaignApprovalService;
use App\Services\Marketing\CampaignLandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LandingApprovalController extends Controller
{
    public function __construct(
        private readonly CampaignApprovalService $approval,
        private readonly MarketingSourceRepository $sources,
    ) {}

    public function index(
        Request $request,
        CampaignLandingService $landing,
    ): Response {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $this->approval->canViewApprovals($user)), 403);

        $campaigns = $this->sources->rootCampaignsForApproval($user)
            ->filter(fn (MarketingSource $campaign): bool => (bool) $campaign->landingConnection)
            ->map(fn (MarketingSource $c) => $this->presentForApproval($c, $landing))
            ->values();

        return Inertia::render('Pushsale/Pages/Marketing/LandingApprovalPage', [
            'campaigns' => $campaigns,
            'products' => Product::query()
                ->where('is_active', true)
                ->where('available_marketing', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'type', 'unit_price']),
            'highlightCampaignId' => $request->integer('campaign') ?: null,
            'fieldMapping' => $this->fieldMappingGuide(),
            'canApprove' => true,
            'approveBaseUrl' => '/admin/marketing/landing-approvals',
            'activeMenuCode' => '2.4.3',
        ]);
    }

    public function approve(Request $request, MarketingSource $campaign): RedirectResponse
    {
        $connection = $campaign->landingConnection()->with(['sources', 'products'])->first();
        abort_unless($connection, 404);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn ($query) => $query
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->where('available_marketing', true))],
            'budget_type' => ['nullable', Rule::in(['total', 'daily'])],
            'budget_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999999'],
            'budget_start_date' => ['nullable', 'date'],
            'budget_end_date' => ['nullable', 'date', 'after_or_equal:budget_start_date'],
        ]);

        $productIds = collect($validated['product_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return back()->withErrors(['product_ids' => 'Cần chọn ít nhất 1 sản phẩm hoặc gói sản phẩm trước khi duyệt.']);
        }

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->get(['id', 'type']);

        $mainSource = $connection->sources->firstWhere('source_type', LandingConnectionSource::TYPE_MAIN)
            ?? $connection->sources->first();

        $connection->products()->delete();
        foreach ($productIds as $index => $productId) {
            $product = $products->firstWhere('id', $productId);
            LandingConnectionProduct::query()->create([
                'company_id' => $connection->company_id,
                'landing_connection_id' => $connection->id,
                'landing_connection_source_id' => $mainSource?->id,
                'product_id' => $productId,
                'item_type' => $product?->type === 'combo' ? 'combo' : 'product',
                'quantity' => 1,
                'is_default' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        $budgetType = (string) ($validated['budget_type'] ?? $connection->budget_type ?: 'total');
        $budgetAmount = max(0, (int) ($validated['budget_amount'] ?? $connection->budget_amount ?? 0));
        $budgetStart = filled($validated['budget_start_date'] ?? null) ? (string) $validated['budget_start_date'] : null;
        $budgetEnd = filled($validated['budget_end_date'] ?? null) ? (string) $validated['budget_end_date'] : null;
        $budgetTotal = $budgetAmount;
        if ($budgetType === 'daily' && $budgetStart && $budgetEnd) {
            $budgetTotal = $budgetAmount * (Carbon::parse($budgetStart)->startOfDay()->diffInDays(Carbon::parse($budgetEnd)->startOfDay()) + 1);
        }

        $connection->update([
            'budget_type' => $budgetType,
            'budget_amount' => $budgetAmount,
            'budget_start_date' => $budgetStart,
            'budget_end_date' => $budgetEnd,
            'updated_by_user_id' => $request->user()->id,
        ]);

        $campaign->update([
            'product_id' => $productIds->first(),
            'budget' => $budgetTotal,
        ]);

        $this->approval->approve($request->user(), $campaign->fresh(), $productIds->first());

        return back()->with('success', __('messages.landing_approved'));
    }

    public function reject(RejectCampaignRequest $request, MarketingSource $campaign): RedirectResponse
    {
        $this->approval->reject($request->user(), $campaign, $request->validated('reason'));

        return back()->with('success', __('messages.landing_rejected'));
    }

    /** @return array<string, mixed> */
    private function presentForApproval(MarketingSource $c, CampaignLandingService $landing): array
    {
        $connection = $c->landingConnection;
        $mainSource = $connection?->sources->firstWhere('source_type', LandingConnectionSource::TYPE_MAIN);
        $submissionUrl = $connection && $mainSource
            ? url('/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$mainSource->public_token.'/submit')
            : ($c->webhook_token ? $landing->webhookUrl($c) : null);

        $connectionProducts = $connection?->products ?? collect();
        $budgetType = $connection?->budget_type ?: 'total';
        $budgetAmount = (int) ($connection?->budget_amount ?? $c->budget ?? 0);

        return [
            'id' => $c->id,
            'connection_id' => $connection?->id,
            'name' => $c->name,
            'product' => $c->product?->name,
            'product_ids' => $connectionProducts->pluck('product_id')->filter()->values(),
            'products' => $connectionProducts->map(fn ($mapping): array => [
                'id' => $mapping->id,
                'product_id' => $mapping->product_id,
                'product_name' => $mapping->product?->name,
                'product_sku' => $mapping->product?->sku,
                'item_type' => $mapping->item_type,
            ])->values(),
            'can_be_approved' => $connectionProducts->isNotEmpty() && $c->webhook_token !== null,
            'missing_product' => $connectionProducts->isEmpty(),
            'product_sku' => $c->product?->sku,
            'product_unit_price' => (int) ($c->product?->unit_price ?? 0),
            'marketer' => $c->marketer?->name,
            'marketer_email' => $c->marketer?->email,
            'marketer_user_id' => $c->marketer_user_id,
            'creator' => $c->creator?->name,
            'created_by_user_id' => $c->created_by_user_id,
            'ad_channel' => $c->ad_channel,
            'utm_source' => $c->utm_source,
            'utm_campaign' => $c->utm_campaign,
            'webhook_url' => $submissionUrl,
            'source_url' => $mainSource?->source_url,
            'is_landing_connection' => (bool) $connection,
            'source_count' => (int) ($connection?->sources->count() ?? 0),
            'budget' => $budgetAmount,
            'budget_type' => $budgetType,
            'budget_start_date' => $connection?->budget_start_date?->toDateString(),
            'budget_end_date' => $connection?->budget_end_date?->toDateString(),
            'is_approved' => (bool) $c->is_approved,
            'is_active' => (bool) $c->is_active,
            'created_at' => $c->created_at?->format('d/m/Y H:i'),
            'approved_at' => $c->approved_at?->format('d/m/Y H:i'),
            'approved_by' => $c->approver?->name,
            'rejected_at' => $c->rejected_at?->format('d/m/Y H:i'),
            'rejected_by' => $c->rejector?->name,
            'rejection_reason' => $c->rejection_reason,
        ];
    }

    /** @return list<array{ladipage: string, system: string}> */
    private function fieldMappingGuide(): array
    {
        return [
            ['ladipage' => 'name', 'system' => 'name / customer_name'],
            ['ladipage' => 'phone', 'system' => 'phone / customer_phone (bắt buộc)'],
            ['ladipage' => 'address', 'system' => 'address / shipping_address'],
            ['ladipage' => 'message', 'system' => 'message / note'],
            ['ladipage' => 'ps_flow', 'system' => 'Mã luồng khách; trang upsale lấy từ URL và gửi lại'],
            ['ladipage' => 'submission_id', 'system' => 'Mã submit duy nhất của landing (khuyến nghị)'],
            ['ladipage' => 'field chọn gói', 'system' => 'Tên field/giá trị cấu hình tại trang duyệt kết nối'],
            ['ladipage' => 'giá / sản phẩm', 'system' => 'Không nhận từ landing; hệ thống lấy theo cấu hình backend'],
        ];
    }
}
