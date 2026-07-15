<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectCampaignRequest;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Repositories\MarketingSourceRepository;
use App\Services\Marketing\CampaignApprovalService;
use App\Services\Marketing\CampaignLandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->map(fn (MarketingSource $c) => $this->presentForApproval($c, $landing))
            ->values();

        return Inertia::render('Admin/Landing/Approvals', [
            'campaigns' => $campaigns,
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'unit_price']),
            'highlightCampaignId' => $request->integer('campaign') ?: null,
            'fieldMapping' => $this->fieldMappingGuide(),
            'canApprove' => true,
            'approveBaseUrl' => $user->isAdmin()
                ? '/admin/landing-approvals'
                : '/marketing/landing-approvals',
        ]);
    }

    public function approve(Request $request, MarketingSource $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $this->approval->approve($request->user(), $campaign, $validated['product_id'] ?? null);

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
        $mainSource = $connection?->sources->firstWhere('source_type', 'main');
        $submissionUrl = $connection && $mainSource
            ? url('/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$mainSource->public_token.'/submit')
            : ($c->webhook_token ? $landing->webhookUrl($c) : null);

        return [
            'id' => $c->id,
            'name' => $c->name,
            'product' => $c->product?->name,
            'can_be_approved' => $c->product_id !== null && $c->webhook_token !== null,
            'missing_product' => $c->product_id === null,
            'product_sku' => $c->product?->sku,
            'product_unit_price' => (int) ($c->product?->unit_price ?? 0),
            'marketer' => $c->marketer?->name,
            'marketer_user_id' => $c->marketer_user_id,
            'creator' => $c->creator?->name,
            'created_by_user_id' => $c->created_by_user_id,
            'ad_channel' => $c->ad_channel,
            'utm_source' => $c->utm_source,
            'utm_campaign' => $c->utm_campaign,
            'webhook_url' => $submissionUrl,
            'is_landing_connection' => (bool) $connection,
            'source_count' => (int) ($connection?->sources->count() ?? 0),
            'budget' => (int) $c->budget,
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
            ['ladipage' => 'field chọn gói', 'system' => 'Tên field/giá trị cấu hình tại Kết nối landing'],
            ['ladipage' => 'giá / sản phẩm', 'system' => 'Không nhận từ landing; hệ thống lấy theo cấu hình backend'],
        ];
    }
}
