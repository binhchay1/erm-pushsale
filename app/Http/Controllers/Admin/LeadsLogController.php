<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Repositories\LeadIngestionRepository;
use App\Repositories\UserRepository;
use App\Services\Leads\LeadAllocationModeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadsLogController extends Controller
{
    public function __invoke(
        Request $request,
        LeadIngestionRepository $leadRepo,
        UserRepository $users,
        LeadAllocationModeService $modeService,
    ): Response {
        $filters = [
            'platform' => $request->query('platform'),
            'status' => $request->query('status'),
            'bucket' => $request->query('bucket'),
            'marketing_source_id' => $request->query('marketing_source_id'),
            'search' => $request->query('search'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $exceptionStatuses = LeadIngestionRepository::exceptionStatuses();

        $leads = $leadRepo->paginatedLog($filters)
            ->through(fn (LeadIngestion $lead) => [
                'id' => $lead->id,
                'platform' => $lead->platform,
                'external_id' => $lead->external_id,
                'status' => $lead->status->value,
                'status_label' => $lead->status->label(),
                'is_exception' => in_array($lead->status->value, $exceptionStatuses, true),
                'customer_name' => $lead->customer_name,
                'customer_phone' => $lead->customer_phone,
                'product_interest' => $lead->product_interest,
                'incoming' => $this->incomingSummary($lead),
                'utm_campaign' => $lead->utm_campaign,
                'campaign_name' => $lead->marketingSource?->name,
                'marketing_source_id' => $lead->marketing_source_id,
                'order_code' => $lead->order?->order_code,
                'conflict_order_code' => is_array($lead->payload) ? ($lead->payload['conflict_order_code'] ?? null) : null,
                'error_message' => $lead->error_message,
                'created_at' => $lead->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'filters' => $filters,
            'exceptionCount' => $leadRepo->exceptionCount(),
            'platforms' => array_keys(config('integrations.platforms', [])),
            'statuses' => collect(LeadIngestionStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
            'campaigns' => MarketingSource::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (MarketingSource $c) => ['id' => (string) $c->id, 'name' => $c->name])
                ->all(),
            'salesUsers' => collect($users->nameOptionsByRoles([UserRole::Sales]))
                ->map(fn (array $u) => ['id' => (string) $u['id'], 'name' => $u['name']])
                ->all(),
            'allocateUrl' => $request->is('allocator/*')
                ? '/allocator/leads/allocate'
                : '/admin/leads/allocate',
            'deleteUrlPrefix' => $request->is('allocator/*')
                ? '/allocator/leads'
                : '/admin/leads',
            'listUrl' => $request->is('allocator/*')
                ? '/allocator/workspace'
                : '/admin/leads',
            'canDelete' => ! $request->is('allocator/*'),
            'realtimeChannel' => $request->is('allocator/*') ? 'dashboard.allocator' : 'dashboard.admin',
            'allocationMode' => $modeService->current()->value,
            'allocationModeUrl' => $request->is('allocator/*')
                ? '/allocator/leads/allocation-mode'
                : '/admin/leads/allocation-mode',
            'manualUrl' => $request->is('allocator/*')
                ? '/allocator/leads/manual'
                : '/admin/leads/manual',
            'importUrl' => $request->is('allocator/*')
                ? '/allocator/leads/import'
                : '/admin/leads/import',
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'unit_price'])
                ->map(fn (Product $p) => [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'unit_price' => (int) $p->unit_price,
                ])
                ->all(),
            'importFields' => [
                'name', 'phone', 'address', 'product', 'quantity', 'unit_price', 'discount', 'note', 'utm_source', 'utm_campaign',
            ],
        ]);
    }

    /** Tóm tắt "khách muốn gì" từ payload (item/sản phẩm) để hỗ trợ xử lý tay case ngoại lệ. */
    private function incomingSummary(LeadIngestion $lead): ?string
    {
        $payload = is_array($lead->payload) ? $lead->payload : [];
        $items = $payload['items'] ?? null;

        if (is_array($items) && $items !== []) {
            $names = collect($items)
                ->map(fn ($item) => is_array($item)
                    ? trim((string) ($item['product_name'] ?? $item['name'] ?? ''))
                    : trim((string) $item))
                ->filter()
                ->all();

            if ($names !== []) {
                return implode(', ', $names);
            }
        }

        return $lead->product_interest;
    }
}
