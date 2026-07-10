<?php

namespace App\Services\Leads;

use App\Enums\ClosingStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Events\SaleWorkspaceChanged;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class LeadSupplementReviewService
{
    public const ACKNOWLEDGE = 'acknowledge';

    public const MERGE_ORIGINAL = 'merge_original';

    public const CREATE_SUPPLEMENTAL_ORDER = 'create_supplemental_order';

    public function __construct(
        private readonly LeadOrderFactory $orderFactory,
    ) {}

    /** @return list<string> */
    public static function resolutions(): array
    {
        return [
            self::ACKNOWLEDGE,
            self::MERGE_ORIGINAL,
            self::CREATE_SUPPLEMENTAL_ORDER,
        ];
    }

    public function resolve(
        LeadIngestion $packet,
        User $actor,
        string $resolution,
        ?string $note = null,
    ): LeadIngestion {
        if (! in_array($resolution, self::resolutions(), true)) {
            throw ValidationException::withMessages([
                'resolution' => __('messages.lead_intake.invalid_review_resolution'),
            ]);
        }

        $resolved = DB::transaction(function () use ($packet, $actor, $resolution, $note): LeadIngestion {
            $packet = LeadIngestion::query()
                ->whereKey($packet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $packet->requires_review) {
                throw ValidationException::withMessages([
                    'resolution' => __('messages.lead_intake.not_reviewable'),
                ]);
            }

            if ($packet->reviewed_at) {
                return $packet;
            }

            $originalOrder = $packet->related_order_id
                ? Order::query()->whereKey($packet->related_order_id)->lockForUpdate()->first()
                : null;

            if (! $this->canReview($actor, $packet, $originalOrder)) {
                throw new AuthorizationException(__('messages.lead_intake.review_forbidden'));
            }

            $resolvedOrder = match ($resolution) {
                self::MERGE_ORIGINAL => $this->mergeIntoOriginal($packet, $originalOrder, $actor),
                self::CREATE_SUPPLEMENTAL_ORDER => $this->createSupplementalOrder($packet, $originalOrder, $actor),
                default => null,
            };

            $packet->forceFill([
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
                'review_resolution' => $resolution,
                'review_note' => filled($note) ? trim((string) $note) : null,
                'requires_review' => false,
            ])->save();

            ActivityLogger::log(
                ActivityLogger::LEAD_INGESTED,
                $packet,
                [
                    'reviewed' => true,
                    'resolution' => $resolution,
                    'reviewed_by_user_id' => $actor->id,
                    'related_order_id' => $originalOrder?->id,
                    'resolved_order_id' => $resolvedOrder?->id,
                    'packet_type' => $packet->packet_type?->value,
                    'note' => $note,
                ],
                $packet->customer_name ?? $packet->customer_phone,
                $actor,
            );

            return $packet->fresh(['order', 'relatedOrder']);
        });

        $saleUserId = $resolved->order?->sale_user_id ?? $resolved->relatedOrder?->sale_user_id;
        if ($saleUserId) {
            try {
                event(new SaleWorkspaceChanged((int) $saleUserId));
            } catch (Throwable $exception) {
                Log::warning('Realtime broadcast failed (lead review)', [
                    'lead_ingestion_id' => $resolved->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $resolved;
    }

    /**
     * Người được xử lý packet bổ sung:
     * - Admin hoặc user có Leads:full (allocator/custom permission): mọi packet.
     * - Sale phụ trách đơn gốc: packet của chính mình.
     * - Trưởng nhóm/trưởng bộ phận sale: đơn thuộc cùng team.
     *
     * Kho/marketing/kế toán vẫn được xem trong Hồ sơ khách hàng nhưng không
     * được quyết định gộp/tạo đơn mới, tránh xung đột nghiệp vụ sale.
     */
    public function canReview(User $actor, LeadIngestion $packet, ?Order $order = null): bool
    {
        if ($actor->isAdmin() || $actor->allows(PermissionArea::Leads, PermissionLevel::Full)) {
            return true;
        }

        if (! $actor->isSales() || ! $actor->allows(PermissionArea::Customers, PermissionLevel::Full)) {
            return false;
        }

        $order ??= $packet->relatedOrder ?? $packet->order;
        if (! $order) {
            return false;
        }

        if ((int) $order->sale_user_id === (int) $actor->id) {
            return true;
        }

        return in_array($actor->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true)
            && $actor->team_id !== null
            && (int) $order->team_id === (int) $actor->team_id;
    }

    private function mergeIntoOriginal(
        LeadIngestion $packet,
        ?Order $order,
        User $actor,
    ): Order {
        if (! $order) {
            throw ValidationException::withMessages([
                'resolution' => __('messages.lead_intake.related_order_missing'),
            ]);
        }

        if (! $this->canSafelyMerge($order)) {
            throw ValidationException::withMessages([
                'resolution' => __('messages.lead_intake.order_no_longer_mergeable', [
                    'code' => $order->order_code,
                ]),
            ]);
        }

        $normalized = $this->orderFactory->normalizedFromLead($packet);
        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        $discount = max(0, (int) ($normalized['discount'] ?? 0));

        if ($items === [] && $discount === 0) {
            throw ValidationException::withMessages([
                'resolution' => __('messages.lead_intake.upsell_has_no_items'),
            ]);
        }

        $order = $this->orderFactory->appendItems($order, $items, $discount, 'late-upsell-review');

        $packet->forceFill([
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Upsell,
            'counts_as_lead' => false,
            'order_id' => $order->id,
            'related_order_id' => null,
            'error_message' => null,
            'processed_at' => now(),
        ])->save();

        $this->recordHistory(
            $order,
            $packet,
            $actor,
            'landing_late_upsell_manually_merged',
            __('messages.lead_intake.review_merge_history'),
            $items,
        );

        if ($order->sale_user_id) {
            NotificationService::push(
                (int) $order->sale_user_id,
                'lead',
                null,
                null,
                '/sales/customers?search='.$order->customer_phone,
                [
                    'variant' => 'late_upsell_merged',
                    'order_code' => $order->order_code,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                ],
            );
        }

        return $order;
    }

    private function createSupplementalOrder(
        LeadIngestion $packet,
        ?Order $originalOrder,
        User $actor,
    ): Order {
        if (! $originalOrder) {
            throw ValidationException::withMessages([
                'resolution' => __('messages.lead_intake.related_order_missing'),
            ]);
        }

        $normalized = $this->orderFactory->normalizedFromLead($packet);
        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];

        if ($items === []) {
            throw ValidationException::withMessages([
                'resolution' => __('messages.lead_intake.upsell_has_no_items'),
            ]);
        }

        $normalized['customer_name'] = $packet->customer_name ?: $originalOrder->customer_name;
        $normalized['customer_phone'] = $packet->customer_phone ?: $originalOrder->customer_phone;
        $normalized['shipping_address'] = $normalized['shipping_address'] ?: $originalOrder->effectiveShippingAddress();
        $normalized['shipping_notes'] = $normalized['shipping_notes'] ?: $originalOrder->shipping_notes;
        $normalized['utm_source'] = $packet->utm_source;
        $normalized['utm_campaign'] = $packet->utm_campaign;
        $normalized['item_origin'] = 'late-upsell-review';

        $sale = $originalOrder->sale_user_id
            ? User::query()->find($originalOrder->sale_user_id)
            : null;

        // Không chạy lại routing: đơn bổ sung luôn giữ sale của đơn gốc hoặc để
        // chưa phân công nếu đơn gốc chưa có sale.
        $newOrder = $this->orderFactory->createFromLead($packet, $normalized, $sale);
        $newOrder->forceFill([
            'team_id' => $originalOrder->team_id,
            'warehouse_id' => $originalOrder->warehouse_id,
            'shipping_address_2' => $originalOrder->shipping_address_2,
            'receiver_name' => $originalOrder->receiver_name,
            'receiver_phone' => $originalOrder->receiver_phone,
            'is_returning_customer' => true,
        ])->save();

        $packet->forceFill([
            'status' => LeadIngestionStatus::Processed,
            'order_id' => $newOrder->id,
            'related_order_id' => $originalOrder->id,
            'error_message' => null,
            'processed_at' => now(),
        ])->save();

        $this->recordHistory(
            $originalOrder,
            $packet,
            $actor,
            'landing_late_upsell_created_order',
            __('messages.lead_intake.review_create_order_history', ['code' => $newOrder->order_code]),
            $items,
        );
        $this->recordHistory(
            $newOrder,
            $packet,
            $actor,
            'landing_supplemental_order_created',
            __('messages.lead_intake.review_supplemental_order_history', ['code' => $originalOrder->order_code]),
            $items,
        );

        if ($newOrder->sale_user_id) {
            NotificationService::push(
                (int) $newOrder->sale_user_id,
                'lead',
                null,
                null,
                '/sales/customers?search='.$newOrder->customer_phone,
                [
                    'variant' => 'supplemental_order_created',
                    'order_code' => $newOrder->order_code,
                    'original_order_code' => $originalOrder->order_code,
                    'customer_name' => $newOrder->customer_name,
                    'customer_phone' => $newOrder->customer_phone,
                ],
            );
        }

        return $newOrder->fresh(['items']);
    }

    /**
     * Mã lý do khiến đơn gốc không còn an toàn để gộp packet mua thêm.
     *
     * Frontend chỉ dùng mã này để giải thích quyết định cho người vận hành;
     * quyền và điều kiện cuối cùng vẫn luôn được kiểm tra lại trong transaction.
     */
    public function mergeBlockReason(Order $order): ?string
    {
        $closingStatus = (string) ($order->closing_status ?? ClosingStatus::Open->value);

        if ($order->closed_at !== null || $closingStatus === ClosingStatus::Closed->value) {
            return 'order_closed';
        }

        if ($closingStatus === ClosingStatus::Cancelled->value) {
            return 'order_cancelled';
        }

        if ($order->inventory_deducted_at !== null) {
            return 'inventory_deducted';
        }

        if (filled($order->tracking_number)) {
            return 'tracking_created';
        }

        $hasShipments = $order->relationLoaded('shipments')
            ? $order->shipments->isNotEmpty()
            : $order->shipments()->exists();

        if ($hasShipments) {
            return 'shipment_created';
        }

        return null;
    }

    public function canSafelyMerge(Order $order): bool
    {
        return $this->mergeBlockReason($order) === null;
    }

    /** @param list<array<string, mixed>> $items */
    private function recordHistory(
        Order $order,
        LeadIngestion $packet,
        User $actor,
        string $action,
        string $note,
        array $items,
    ): void {
        OrderOperationHistory::query()->create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role?->value ?? (string) $actor->role,
            'action' => $action,
            'operation_stage_before' => $order->operation_stage,
            'operation_stage_after' => $order->operation_stage,
            'operation_result' => $order->operation_result,
            'next_operation_at' => $order->next_operation_at,
            'note' => $note,
            'metadata' => [
                'lead_ingestion_id' => $packet->id,
                'items' => collect($items)->map(fn (array $item): array => [
                    'name' => $item['product_name'] ?? $item['name'] ?? null,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'unit_price' => max(0, (int) ($item['unit_price'] ?? 0)),
                ])->values()->all(),
            ],
            'created_at' => now(),
        ]);
    }
}
