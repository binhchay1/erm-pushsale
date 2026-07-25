<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Repositories\LeadIngestionRepository;
use App\Repositories\UserRepository;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\LeadSupplementReviewService;
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
        LeadSupplementReviewService $reviewService,
    ): Response {
        $filters = [
            'platform' => $request->query('platform'),
            'status' => $request->query('status'),
            'bucket' => $request->query('bucket'),
            'packet_type' => $request->query('packet_type'),
            'review' => $request->query('review'),
            'marketing_source_id' => $request->query('marketing_source_id'),
            'search' => $request->query('search'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $exceptionStatuses = LeadIngestionRepository::exceptionStatuses();

        if ($request->is('marketing/*') && $request->user()?->role === UserRole::Marketing) {
            $filters['marketer_user_id'] = $request->user()->id;
        }

        $leads = $leadRepo->paginatedLog($filters)
            ->through(function (LeadIngestion $lead) use ($exceptionStatuses, $reviewService): array {
                $order = $lead->effectiveOrder();
                $stage = $order ? OperationStage::tryFrom((string) $order->operation_stage) : null;
                $result = $order ? OperationResult::tryFromStored($order->operation_result) : null;
                $packetAddress = $this->payloadString($lead, ['shipping_address', 'address', 'customer_address']);
                $packetMessage = $this->payloadString($lead, ['message', 'note', 'customer_note', 'content']);
                $isSupplemental = $lead->isSupplementalPacket();

                return [
                    'id' => $lead->id,
                    'platform' => $lead->platform,
                    'external_id' => $lead->external_id,
                    'status' => $lead->status->value,
                    'status_label' => $lead->status->label(),
                    'packet_type' => $lead->packet_type?->value ?? LeadPacketType::Lead->value,
                    'packet_type_label' => ($lead->packet_type ?? LeadPacketType::Lead)->label(),
                    'counts_as_lead' => (bool) $lead->counts_as_lead,
                    'is_supplemental' => $isSupplemental,
                    'requires_review' => (bool) $lead->requires_review,
                    'reviewed_at' => $lead->reviewed_at?->toIso8601String(),
                    'review_resolution' => $lead->review_resolution,
                    'review_note' => $lead->review_note,
                    'can_merge_original' => $lead->requires_review
                        && $lead->related_order_id
                        && $order
                        && $reviewService->canSafelyMerge($order),
                    'can_create_supplemental_order' => $lead->requires_review
                        && $lead->related_order_id
                        && $order !== null
                        && $this->leadProducts($lead) !== [],
                    'is_exception' => ($lead->requires_review && ! $lead->reviewed_at) || in_array($lead->status->value, $exceptionStatuses, true),
                    'customer_name' => $lead->customer_name,
                    'customer_phone' => $lead->customer_phone,
                    'address' => $packetAddress ?: $order?->effectiveShippingAddress(),
                    'address_inherited' => ! $packetAddress && filled($order?->effectiveShippingAddress()),
                    'message' => $packetMessage ?: $order?->customer_note,
                    'message_inherited' => ! $packetMessage && filled($order?->customer_note),
                    'product_interest' => $lead->product_interest,
                    'incoming' => $this->incomingSummary($lead),
                    'products' => $this->leadProducts($lead),
                    'utm_campaign' => $lead->utm_campaign,
                    'campaign_name' => $lead->marketingSource?->name ?? $order?->marketingSource?->name,
                    'marketing_source_id' => $lead->marketing_source_id,
                    'order_id' => $lead->order_id ? (string) $lead->order_id : null,
                    'related_order_id' => $lead->related_order_id ? (string) $lead->related_order_id : null,
                    'order_code' => $order?->order_code,
                    'order_relation' => $lead->order_id ? 'merged' : ($lead->related_order_id ? 'related' : null),
                    'parent_ingestion_id' => $lead->parent_ingestion_id,
                    'conflict_order_code' => $order?->order_code
                        ?? (is_array($lead->payload) ? ($lead->payload['conflict_order_code'] ?? null) : null),
                    'sale_name' => $order?->saleUser?->name,
                    'sale_team' => $order?->team?->name,
                    'assigned_at' => $order?->assigned_at?->toIso8601String(),
                    'operation_stage' => $stage?->label() ?? $order?->operation_stage,
                    'operation_result' => $result?->label() ?? $order?->operation_result,
                    'closed_at' => $order?->closed_at?->toIso8601String(),
                    'error_message' => $lead->error_message,
                    'created_at' => $lead->created_at?->toIso8601String(),
                    'processed_at' => $lead->processed_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'filters' => $filters,
            'exceptionCount' => $leadRepo->exceptionCount(),
            'platforms' => array_keys(config('integrations.platforms', [])),
            'statuses' => collect(LeadIngestionStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
            'packetTypes' => collect(LeadPacketType::cases())
                ->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])
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
            'allocateUrl' => match (true) {
                $request->is('allocator/*') => '/allocator/leads/allocate',
                $request->is('marketing/*') => '/marketing/leads/allocate',
                default => '/admin/leads/allocate',
            },
            'deleteUrlPrefix' => match (true) {
                $request->is('allocator/*') => '/allocator/leads',
                $request->is('marketing/*') => '/marketing/leads',
                default => '/admin/leads',
            },
            'listUrl' => match (true) {
                $request->is('allocator/*') => '/allocator/workspace',
                $request->is('marketing/*') => '/marketing/leads',
                default => '/admin/leads',
            },
            'canDelete' => ! $request->is('allocator/*') && ! $request->is('marketing/*'),
            'canReview' => ! $request->is('marketing/*'),
            'reviewUrlPrefix' => match (true) {
                $request->is('allocator/*') => '/allocator/leads',
                default => '/admin/leads',
            },
            'canAllocate' => ! $request->is('marketing/*'),
            'showAllocationTools' => ! $request->is('marketing/*'),
            'realtimeChannel' => match (true) {
                $request->is('allocator/*') => 'dashboard.allocator',
                $request->is('marketing/*') => 'dashboard.marketing',
                default => 'dashboard.admin',
            },
            'allocationMode' => $modeService->current()->value,
            'allocationModeUrl' => match (true) {
                $request->is('allocator/*') => '/allocator/leads/allocation-mode',
                $request->is('marketing/*') => '/marketing/leads/allocation-mode',
                default => '/admin/leads/allocation-mode',
            },
            'manualUrl' => match (true) {
                $request->is('allocator/*') => '/allocator/leads/manual',
                $request->is('marketing/*') => '/marketing/leads/manual',
                default => '/admin/leads/manual',
            },
            'importUrl' => match (true) {
                $request->is('allocator/*') => '/allocator/leads/import',
                $request->is('marketing/*') => '/marketing/leads/import',
                default => '/admin/leads/import',
            },
            'templateUrl' => match (true) {
                $request->is('allocator/*') => '/allocator/leads/import-template',
                $request->is('marketing/*') => '/marketing/leads/import-template',
                default => '/admin/leads/import-template',
            },
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'sku', 'unit_price'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type ?? 'product',
                    'sku' => $p->sku,
                    'unit_price' => (int) $p->unit_price,
                ])
                ->all(),
            'importFields' => [
                'name', 'phone', 'address', 'product', 'quantity', 'unit_price', 'discount', 'note', 'utm_source', 'utm_campaign',
            ],
            'canManageTemplate' => $request->user()?->role === UserRole::Admin,
            'companyTemplate' => [
                'name' => $request->user()?->company?->lead_import_template_name,
                'has' => filled($request->user()?->company?->lead_import_template_path),
            ],
            'templateUploadUrl' => '/admin/company/lead-template',
            'templateRemoveUrl' => '/admin/company/lead-template',
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

        $mapping = is_array($payload['_landing_webhook_mapping'] ?? null) ? $payload['_landing_webhook_mapping'] : [];
        $candidateText = $mapping['product_candidate_text'] ?? null;
        if (is_scalar($candidateText) && trim((string) $candidateText) !== '') {
            return trim((string) $candidateText);
        }

        $unmapped = collect((array) ($mapping['unmapped_product_fields'] ?? []))
            ->map(fn ($field) => is_array($field) ? trim((string) ($field['value'] ?? '')) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($unmapped !== []) {
            return 'Chưa map: '.implode(', ', $unmapped);
        }

        return $lead->product_interest;
    }
    /** @param list<string> $keys */
    private function payloadString(LeadIngestion $lead, array $keys): ?string
    {
        $payload = is_array($lead->payload) ? $lead->payload : [];

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /** @return list<array{name: string, quantity: int, unit_price: int}> */
    private function leadProducts(LeadIngestion $lead): array
    {
        /*
         * Nhật ký lead hiển thị sản phẩm của CHÍNH packet. Chỉ packet lead chính
         * đã tạo order mới hiển thị toàn bộ item của order; late/upsell không được
         * lấy nhầm toàn bộ đơn cũ.
         */
        if ($lead->counts_as_lead && $lead->order_id && $lead->order?->items?->isNotEmpty()) {
            return $lead->order->items->map(fn ($item) => [
                'name' => (string) $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
            ])->values()->all();
        }

        $payload = is_array($lead->payload) ? $lead->payload : [];
        $items = $payload['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item): ?array {
                if (is_string($item) && trim($item) !== '') {
                    return ['name' => trim($item), 'quantity' => 1, 'unit_price' => 0];
                }

                if (! is_array($item)) {
                    return null;
                }

                $name = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'unit_price' => max(0, (int) ($item['unit_price'] ?? $item['price'] ?? 0)),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }


}
