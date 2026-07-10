<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Services\Leads\LeadOrderFactory;
use App\Services\Leads\LeadSupplementReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CustomerSupplementPacketController extends Controller
{
    public function index(
        Request $request,
        Order $order,
        LeadSupplementReviewService $reviewService,
        LeadOrderFactory $orderFactory,
    ): JsonResponse {
        $order->loadMissing([
            'saleUser:id,name',
            'team:id,name',
            'items:id,order_id,quantity,unit_price',
            'shipments:id,order_id',
        ]);

        $packets = LeadIngestion::query()
            ->with([
                'reviewedBy:id,name',
                'order:id,order_code,sale_user_id,team_id',
                'relatedOrder:id,order_code,sale_user_id,team_id',
            ])
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
            'order' => $this->presentOrder($order, $reviewService),
            'summary' => $this->presentSummary($packets),
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

        $packet->load([
            'reviewedBy:id,name',
            'order:id,order_code,sale_user_id,team_id',
            'relatedOrder:id,order_code,sale_user_id,team_id',
        ]);

        $order = $order->fresh([
            'saleUser:id,name',
            'team:id,name',
            'items:id,order_id,quantity,unit_price',
            'shipments:id,order_id',
        ]);

        return response()->json([
            'message' => __('messages.lead_intake.review_marked'),
            'order' => $this->presentOrder($order, $reviewService),
            'packet' => $this->present(
                $packet,
                $order,
                $request,
                $reviewService,
                $orderFactory,
            ),
            'pending_count' => LeadIngestion::query()
                ->where('counts_as_lead', false)
                ->where('requires_review', true)
                ->whereNull('reviewed_at')
                ->where(function ($query) use ($order): void {
                    $query->where('order_id', $order->id)
                        ->orWhere('related_order_id', $order->id);
                })
                ->count(),
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
        $items = collect($normalized['items'] ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(static function (array $item): array {
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = max(0, (int) ($item['unit_price'] ?? 0));

                return [
                    'name' => $item['product_name'] ?? $item['name'] ?? '—',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                    'item_type' => $item['item_type'] ?? 'upsell',
                ];
            })
            ->values();

        $discount = max(0, (int) ($normalized['discount'] ?? 0));
        $subtotal = (int) $items->sum('line_total');
        $total = max(0, $subtotal - $discount);

        // Endpoint này luôn được mở từ một đơn cụ thể. Dùng chính model đầy đủ
        // của đơn đó để kiểm tra quyền/trạng thái, tránh relation select thiếu
        // closed_at / tracking_number làm UI hiển thị sai quyền gộp.
        $effectiveOrder = $displayOrder;
        $canReview = $packet->requires_review
            && ! $packet->reviewed_at
            && $reviewService->canReview($request->user(), $packet, $effectiveOrder);
        $mergeBlockReason = $reviewService->mergeBlockReason($effectiveOrder);
        $hasItems = $items->isNotEmpty();
        $hasMergeableContent = $hasItems || $discount > 0;

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
            'items' => $items->all(),
            'item_lines' => $items->count(),
            'item_quantity' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
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
            'can_merge_original' => $canReview && $hasMergeableContent && $mergeBlockReason === null,
            'can_create_supplemental_order' => $canReview && $hasItems,
            'merge_block_reason' => $mergeBlockReason,
            'has_actionable_content' => $hasMergeableContent,
        ];
    }

    /** @return array<string, mixed> */
    private function presentOrder(Order $order, LeadSupplementReviewService $reviewService): array
    {
        $closingStatusValue = (string) ($order->closing_status ?: ClosingStatus::Open->value);
        $closingStatus = ClosingStatus::tryFrom($closingStatusValue);
        $deliveryStatusValue = filled($order->delivery_status) ? (string) $order->delivery_status : null;
        $deliveryStatus = $deliveryStatusValue ? DeliveryStatus::tryFrom($deliveryStatusValue) : null;
        $mergeBlockReason = $reviewService->mergeBlockReason($order);

        return [
            'id' => (string) $order->id,
            'order_code' => $order->order_code,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'sale_name' => $order->saleUser?->name,
            'team_name' => $order->team?->name,
            'closing_status' => $closingStatusValue,
            'closing_status_label' => $closingStatus?->label() ?? $closingStatusValue,
            'delivery_status' => $deliveryStatusValue,
            'delivery_status_label' => $deliveryStatus?->label() ?? $deliveryStatusValue,
            'closed_at' => $order->closed_at?->toIso8601String(),
            'tracking_number' => $order->tracking_number,
            'item_lines' => $order->items->count(),
            'item_quantity' => (int) $order->items->sum('quantity'),
            'total' => (int) $order->total,
            'amount_to_collect' => (int) $order->amount_to_collect,
            'can_accept_merge' => $mergeBlockReason === null,
            'merge_block_reason' => $mergeBlockReason,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $packets */
    private function presentSummary(Collection $packets): array
    {
        $pending = $packets->filter(
            fn (array $packet): bool => $packet['requires_review'] && ! $packet['reviewed_at'],
        );
        $reviewed = $packets->filter(fn (array $packet): bool => (bool) $packet['reviewed_at']);

        return [
            'total_count' => $packets->count(),
            'pending_count' => $pending->count(),
            'reviewed_count' => $reviewed->count(),
            'pending_value' => (int) $pending->sum('total'),
            'total_value' => (int) $packets->sum('total'),
        ];
    }
}
