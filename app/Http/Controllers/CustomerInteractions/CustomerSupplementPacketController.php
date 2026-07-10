<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Services\Leads\LeadOrderFactory;
use App\Services\Leads\LeadSupplementReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerSupplementPacketController extends Controller
{
    public function index(
        Request $request,
        Order $order,
        LeadSupplementReviewService $reviewService,
        LeadOrderFactory $orderFactory,
    ): JsonResponse {
        $packets = LeadIngestion::query()
            ->with(['reviewedBy:id,name', 'order:id,order_code,sale_user_id,team_id', 'relatedOrder:id,order_code,sale_user_id,team_id'])
            ->where('counts_as_lead', false)
            ->where(function ($query) use ($order): void {
                $query->where('order_id', $order->id)
                    ->orWhere('related_order_id', $order->id);
            })
            ->orderByDesc('requires_review')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (LeadIngestion $packet): array => $this->present(
                $packet,
                $order,
                $request,
                $reviewService,
                $orderFactory,
            ))
            ->values();

        return response()->json([
            'order' => [
                'id' => (string) $order->id,
                'order_code' => $order->order_code,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
            ],
            'pending_count' => $packets
                ->filter(fn (array $packet): bool => $packet['requires_review'] && ! $packet['reviewed_at'])
                ->count(),
            'packets' => $packets,
        ]);
    }

    public function store(
        Request $request,
        Order $order,
        LeadIngestion $leadIngestion,
        LeadSupplementReviewService $reviewService,
        LeadOrderFactory $orderFactory,
    ): JsonResponse {
        abort_unless(
            (int) $leadIngestion->order_id === (int) $order->id
                || (int) $leadIngestion->related_order_id === (int) $order->id,
            404,
        );

        $validated = $request->validate([
            'resolution' => ['required', 'string', Rule::in(LeadSupplementReviewService::resolutions())],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $packet = $reviewService->resolve(
            $leadIngestion,
            $request->user(),
            $validated['resolution'],
            $validated['note'] ?? null,
        );

        $packet->load(['reviewedBy:id,name', 'order:id,order_code,sale_user_id,team_id', 'relatedOrder:id,order_code,sale_user_id,team_id']);

        return response()->json([
            'message' => __('messages.lead_intake.review_marked'),
            'packet' => $this->present(
                $packet,
                $order->fresh(),
                $request,
                $reviewService,
                $orderFactory,
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(
        LeadIngestion $packet,
        Order $displayOrder,
        Request $request,
        LeadSupplementReviewService $reviewService,
        LeadOrderFactory $orderFactory,
    ): array {
        $normalized = $orderFactory->normalizedFromLead($packet);
        $effectiveOrder = $packet->relatedOrder ?? $packet->order ?? $displayOrder;
        $canReview = $packet->requires_review
            && ! $packet->reviewed_at
            && $reviewService->canReview($request->user(), $packet, $effectiveOrder);

        return [
            'id' => (string) $packet->id,
            'status' => $packet->status->value,
            'status_label' => $packet->status->label(),
            'packet_type' => $packet->packet_type?->value,
            'packet_type_label' => $packet->packet_type?->label(),
            'external_id' => $packet->external_id,
            'customer_name' => $packet->customer_name ?: $displayOrder->customer_name,
            'customer_phone' => $packet->customer_phone ?: $displayOrder->customer_phone,
            'message' => $normalized['message'] ?? null,
            'items' => collect($normalized['items'] ?? [])->map(static fn (array $item): array => [
                'name' => $item['product_name'] ?? $item['name'] ?? '—',
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => max(0, (int) ($item['unit_price'] ?? 0)),
                'item_type' => $item['item_type'] ?? 'upsell',
            ])->values()->all(),
            'discount' => max(0, (int) ($normalized['discount'] ?? 0)),
            'requires_review' => (bool) $packet->requires_review,
            'reviewed_at' => $packet->reviewed_at?->toIso8601String(),
            'reviewed_by' => $packet->reviewedBy?->name,
            'review_resolution' => $packet->review_resolution,
            'review_note' => $packet->review_note,
            'error_message' => $packet->error_message,
            'created_at' => $packet->created_at?->toIso8601String(),
            'processed_at' => $packet->processed_at?->toIso8601String(),
            'order_code' => $packet->order?->order_code,
            'related_order_code' => $packet->relatedOrder?->order_code,
            'can_review' => $canReview,
            'can_merge_original' => $canReview && $effectiveOrder && $reviewService->canSafelyMerge($effectiveOrder),
            'can_create_supplemental_order' => $canReview && $effectiveOrder !== null,
        ];
    }
}
