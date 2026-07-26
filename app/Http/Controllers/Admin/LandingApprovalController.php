<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSource;
use App\Models\Product;
use App\Services\Marketing\CampaignLandingService;
use App\Services\Marketing\LandingConnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LandingApprovalController extends Controller
{
    public function __construct(private readonly LandingConnectionManager $manager) {}

    public function index(Request $request, CampaignLandingService $landing): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->allows(\App\Enums\PermissionArea::Marketing, \App\Enums\PermissionLevel::View) || $user->allows(\App\Enums\PermissionArea::Integrations, \App\Enums\PermissionLevel::View)), 403);

        $connections = LandingConnection::query()
            ->with($this->manager->relations())
            ->whereIn('connection_type', ['landing', 'website', 'facebook'])
            ->latest('id')
            ->get()
            ->map(fn (LandingConnection $connection): array => $this->presentForApproval($connection, $landing))
            ->values();

        return Inertia::render('Admin/Marketing/LandingApprovalPage', [
            'campaigns' => $connections,
            'products' => Product::query()
                ->where('is_active', true)
                ->where('available_marketing', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'type', 'unit_price']),
            'highlightCampaignId' => $request->integer('campaign') ?: $request->integer('connection') ?: null,
            'fieldMapping' => $this->fieldMappingGuide(),
            'canApprove' => true,
            'approveBaseUrl' => '/admin/marketing/landing-approvals',
            'activeMenuCode' => '2.4.3',
        ]);
    }

    public function approve(Request $request, LandingConnection $connection): RedirectResponse
    {
        $request->merge([
            'budget_amount' => $this->moneyToInt($request->input('budget_amount')),
        ]);

        abort_unless($request->user() && ($request->user()->isAdmin() || $request->user()->allows(\App\Enums\PermissionArea::Marketing, \App\Enums\PermissionLevel::Full) || $request->user()->allows(\App\Enums\PermissionArea::Integrations, \App\Enums\PermissionLevel::Full)), 403);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn ($query) => $query
                ->where('company_id', (int) $connection->company_id)
                ->where('is_active', true)
                ->where('available_marketing', true))],
            'budget_type' => ['nullable', Rule::in(['total', 'daily'])],
            'budget_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999999'],
            'budget_start_date' => ['nullable', 'date'],
            'budget_end_date' => ['nullable', 'date', 'after_or_equal:budget_start_date'],
        ]);

        try {
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

            $connection->loadMissing(['sources']);
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

            $this->manager->approve($connection->fresh($this->manager->relations()), $validated, $request->user());
        } catch (Throwable $exception) {
            Log::error('landing_approvals.approve_failed', [
                'connection_id' => $connection->id,
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return back()
                ->withErrors(['approval' => app()->isProduction() ? 'Không duyệt được kết nối landing. Chi tiết đã ghi vào log.' : get_class($exception).': '.$exception->getMessage()])
                ->with('error', 'Không duyệt được kết nối landing.');
        }

        return back()->with('success', __('messages.landing_approved'));
    }

    public function reject(Request $request, LandingConnection $connection): RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isAdmin() || $request->user()->allows(\App\Enums\PermissionArea::Marketing, \App\Enums\PermissionLevel::Full) || $request->user()->allows(\App\Enums\PermissionArea::Integrations, \App\Enums\PermissionLevel::Full)), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->manager->reject($connection, (string) $validated['reason'], $request->user());

        return back()->with('success', __('messages.landing_rejected'));
    }


    private function moneyToInt(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value)) {
            return max(0, (int) $value);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value) ?: '0';

        return max(0, min(999999999999999, (int) $digits));
    }

    /** @return array<string, mixed> */
    private function presentForApproval(LandingConnection $connection, CampaignLandingService $landing): array
    {
        $connection->loadMissing($this->manager->relations());
        $campaign = $connection->marketingSource;
        $mainSource = $connection->sources->firstWhere('source_type', LandingConnectionSource::TYPE_MAIN)
            ?? $connection->sources->first();
        $submissionUrl = $mainSource
            ? url('/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$mainSource->public_token.'/submit')
            : ($campaign?->webhook_token ? $landing->webhookUrl($campaign) : null);

        $connectionProducts = $connection->products ?? collect();
        $budgetType = $connection->budget_type ?: 'total';
        $budgetAmount = (int) ($connection->budget_amount ?? $campaign?->budget ?? 0);
        $metadata = (array) ($connection->metadata ?? []);

        return [
            'id' => $connection->id,
            'connection_id' => $connection->id,
            'campaign_id' => $campaign?->id,
            'name' => $connection->name,
            'product' => $campaign?->product?->name,
            'product_ids' => $connectionProducts->pluck('product_id')->filter()->values(),
            'products' => $connectionProducts->map(fn ($mapping): array => [
                'id' => $mapping->id,
                'product_id' => $mapping->product_id,
                'product_name' => $mapping->product?->name,
                'product_sku' => $mapping->product?->sku,
                'item_type' => $mapping->item_type,
            ])->values(),
            'can_be_approved' => $connectionProducts->isNotEmpty(),
            'missing_product' => $connectionProducts->isEmpty(),
            'product_sku' => $campaign?->product?->sku,
            'product_unit_price' => (int) ($campaign?->product?->unit_price ?? 0),
            'marketer' => $connection->marketer?->name,
            'marketer_email' => $connection->marketer?->email,
            'marketer_user_id' => $connection->marketer_user_id,
            'creator' => $connection->createdBy?->name,
            'created_by_user_id' => $connection->created_by_user_id,
            'ad_channel' => $connection->ad_channel,
            'utm_source' => $campaign?->utm_source,
            'utm_campaign' => $campaign?->utm_campaign,
            'webhook_url' => $submissionUrl,
            'source_url' => $mainSource?->source_url,
            'is_landing_connection' => true,
            'source_count' => (int) ($connection->sources->count() ?? 0),
            'budget' => $budgetAmount,
            'budget_type' => $budgetType,
            'budget_start_date' => $connection->budget_start_date?->toDateString(),
            'budget_end_date' => $connection->budget_end_date?->toDateString(),
            'is_approved' => (bool) $connection->is_approved,
            'is_active' => (bool) $connection->is_active,
            'created_at' => $connection->created_at?->format('d/m/Y H:i'),
            'approved_at' => $connection->approved_at?->format('d/m/Y H:i'),
            'approved_by' => $connection->approver?->name ?: $campaign?->approver?->name,
            'rejected_at' => $metadata['rejected_at'] ?? $campaign?->rejected_at?->format('d/m/Y H:i'),
            'rejected_by' => $metadata['rejected_by_user_id'] ?? $campaign?->rejector?->name,
            'rejection_reason' => $metadata['rejection_reason'] ?? $campaign?->rejection_reason,
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
