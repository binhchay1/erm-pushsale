<?php

namespace App\Services\Leads;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\UserRole;
use App\Events\LeadIngested;
use App\Events\LeadPoolChanged;
use App\Events\SaleWorkspaceChanged;
use App\Integrations\IntegrationDriverFactory;
use App\Jobs\Leads\FinalizeLandingLeadJob;
use App\Jobs\Leads\FinalizeLandingSupplementPacketJob;
use App\Models\LandingSession;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\Customers\CustomerPhoneAssignmentService;
use App\Services\Marketing\MarketingStatsBroadcaster;
use App\Services\NotificationService;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadIngestionService
{
    public function __construct(
        protected LeadRoutingService $routing,
        protected LeadOrderFactory $orderFactory,
        protected LeadSanitizer $sanitizer,
        protected LeadAllocationResolver $allocationResolver,
        protected LandingUpsellService $landingUpsell,
        protected MarketingStatsBroadcaster $marketingStats,
        protected LeadDuplicatePolicy $duplicatePolicy,
        protected CustomerPhoneAssignmentService $phoneAssignment,
    ) {}

    /**
     * Lead từ URL API riêng của chiến dịch Landing (Ladipage) — form đặt hàng đầu tiên.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingestForCampaign(
        LeadPayloadNormalizer $driver,
        MarketingSource $campaign,
        array $rawPayload,
    ): LeadIngestion {
        return $this->handleLandingPacket($driver, $campaign, $rawPayload, isUpsell: false);
    }

    /**
     * Upsale từ trang cảm ơn (Ladipage): gộp vào ĐƠN/LEAD đang mở của cùng khách,
     * KHÔNG tạo số mới để tránh trùng & lệch chia số.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingestUpsellForCampaign(
        LeadPayloadNormalizer $driver,
        MarketingSource $campaign,
        array $rawPayload,
    ): LeadIngestion {
        return $this->handleLandingPacket($driver, $campaign, $rawPayload, isUpsell: true);
    }

    /**
     * Cửa ngõ chung cho mọi gói tin Landing (form đầu + upsale trang cảm ơn).
     *
     * Nguyên tắc gộp: cùng (chiến dịch + SĐT) trong cửa sổ gom → 1 đơn duy nhất.
     *   1) Có lead chờ chia (chưa tạo đơn, chia tay) → cộng dồn vào lead đó.
     *   2) Có đơn vừa tạo trong cửa sổ gom / đang chờ upsale → cộng dồn vào đơn.
     *   3) Chưa có gì → tạo lead + chia số ngay; đơn giữ cửa sổ upsale trên tác nghiệp.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    protected function handleLandingPacket(
        LeadPayloadNormalizer $driver,
        MarketingSource $campaign,
        array $rawPayload,
        bool $isUpsell,
    ): LeadIngestion {
        $normalized = $driver->normalize($rawPayload);
        $isSupplemental = $isUpsell || $this->looksLikeSupplementalPacket($rawPayload, $normalized);

        // Chỉ lead chính mới dùng tên mặc định. Packet upsell/follow-up để trống
        // để khi liên kết đơn có thể kế thừa chính xác tên khách từ đơn gốc.
        if (! $isSupplemental && blank($normalized['customer_name'] ?? null)) {
            $normalized['customer_name'] = __('messages.lead_intake.guest_name');
        }

        $packetType = $isUpsell
            ? LeadPacketType::Upsell
            : ($isSupplemental ? LeadPacketType::FollowUp : LeadPacketType::Lead);

        if ($this->sanitizer->exceedsPayloadLimit($rawPayload)) {
            return $this->recordFailed(
                $driver->platform(),
                ['oversized' => true],
                __('messages.lead_intake.payload_too_large'),
                $packetType,
                ! $isSupplemental,
            );
        }

        if ($this->sanitizer->hasHoneypot($rawPayload)) {
            return $this->recordFailed(
                $driver->platform(),
                $rawPayload,
                __('messages.lead_intake.honeypot'),
                $packetType,
                ! $isSupplemental,
            );
        }

        // Giữ UTM thật landing gửi lên; chỉ fallback sang cấu hình campaign khi payload không có.
        // Marketing raw dashboard đọc inbound_events nên không bị ảnh hưởng bởi bước normalize này.
        $normalized['utm_campaign'] = $normalized['utm_campaign'] ?? $campaign->utm_campaign;
        $normalized['utm_source'] = $normalized['utm_source'] ?? $campaign->utm_source ?? 'ladipage';

        $externalId = (string) ($normalized['external_id'] ?? '');
        $packetExternalId = $this->packetExternalId($externalId, $packetType);

        if ($packetExternalId !== '') {
            $existing = LeadIngestion::query()
                ->where('platform', $driver->platform())
                ->where('external_id', $packetExternalId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $phone = $this->sanitizer->normalizePhone($normalized['customer_phone'] ?? null);
        $session = $this->resolveSession($campaign, $rawPayload, $phone);

        if ($phone) {
            $normalized['customer_phone'] = $phone;
        }

        /*
         * Packet bổ sung có thể tới trước packet chính vì queue có nhiều worker.
         * Không bao giờ tạo đơn độc lập hoặc chạy chia số từ packet upsell.
         */
        if ($isSupplemental) {
            if ($mergeLead = $phone ? $this->findActiveGatheringLead($campaign, $phone, $session) : null) {
                return $this->mergePacketIntoLead(
                    $driver,
                    $mergeLead,
                    $campaign,
                    $normalized,
                    $rawPayload,
                    $packetExternalId,
                    $packetType,
                    $session,
                );
            }

            if ($order = $this->findRecentOrderForMerge($normalized, $phone, $campaign, $session)) {
                if ($merged = $this->mergePacketIntoOrder(
                    $driver,
                    $order,
                    $campaign,
                    $normalized,
                    $rawPayload,
                    $packetExternalId,
                    $packetType,
                    $session,
                )) {
                    return $merged;
                }
            }

            // Chỉ tin liên kết tường minh ở request đầu. Fallback theo SĐT
            // được trì hoãn để packet chính có thời gian tạo order, tránh nối nhầm
            // upsell đến sớm vào một đơn cũ cùng số điện thoại.
            if ($relatedOrder = $this->findRelatedOrderOutsideMergeWindow(
                $normalized,
                $phone,
                $campaign,
                $session,
                allowPhoneFallback: false,
            )) {
                return $this->recordLateSupplementPacket(
                    $driver,
                    $relatedOrder,
                    $campaign,
                    $normalized,
                    $rawPayload,
                    $packetExternalId,
                    $session,
                );
            }

            if (! $phone && ! $session && blank($normalized['parent_ref'] ?? null)) {
                return $this->recordFailed(
                    $driver->platform(),
                    $rawPayload,
                    __('messages.lead_intake.invalid_phone'),
                    $packetType,
                    false,
                );
            }

            return $this->recordPendingSupplementPacket(
                $driver,
                $campaign,
                $normalized,
                $rawPayload,
                $packetExternalId,
                $packetType,
                $session,
            );
        }

        if (! $phone) {
            return $this->recordFailed($driver->platform(), $rawPayload, __('messages.lead_intake.invalid_phone'));
        }

        $normalized['external_id'] = $packetExternalId !== ''
            ? $packetExternalId
            : (string) ($normalized['external_id'] ?? '');

        // Cùng SĐT + utm_source:
        // - Đang trong cửa sổ chờ upsale + URL khác trang chính → gộp upsale
        // - Cùng URL landing chính → trùng số, đóng cửa sổ 15p
        // - Hết cửa sổ → trùng số
        if ($existing = $this->findOrderByPhoneAndUtm($phone, $normalized['utm_source'] ?? null, $campaign)) {
            if ($this->landingUpsell->canMerge($existing)) {
                if ($this->isSamePrimaryLandingUrl($existing, $rawPayload, $normalized)) {
                    $this->landingUpsell->releaseHold($existing, saleLocked: true);
                    $existing->forceFill(['is_duplicate_phone' => true])->save();
                } else {
                    $normalized['items'] = $this->forceUpsellItems($normalized['items'] ?? []);
                    if ($merged = $this->mergePacketIntoOrder(
                        $driver,
                        $existing,
                        $campaign,
                        $normalized,
                        $rawPayload,
                        $packetExternalId,
                        LeadPacketType::Upsell,
                        $session,
                    )) {
                        return $merged;
                    }
                }
            }
        }

        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign, $session);
    }

    /**
     * So khớp URL/landing source của gói tin với nguồn chính đã tạo đơn.
     * Cùng URL trang chính trong cửa sổ chờ → trùng số (không gộp upsale).
     *
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, mixed>  $normalized
     */
    protected function isSamePrimaryLandingUrl(Order $order, array $rawPayload, array $normalized): bool
    {
        $orderSourceId = $order->landing_connection_source_id
            ? (int) $order->landing_connection_source_id
            : null;
        $incomingSourceId = filled($normalized['landing_connection_source_id'] ?? null)
            ? (int) $normalized['landing_connection_source_id']
            : (filled($rawPayload['landing_connection_source_id'] ?? null)
                ? (int) $rawPayload['landing_connection_source_id']
                : null);

        if ($orderSourceId && $incomingSourceId && $orderSourceId === $incomingSourceId) {
            return true;
        }

        $primaryUrl = $this->normalizeLandingUrl(
            $order->landingConnectionSource?->source_url
                ?? $order->landingConnection?->sources
                    ?->firstWhere('source_type', 'main')
                    ?->source_url
        );

        $incomingUrl = $this->normalizeLandingUrl(
            $rawPayload['url_page']
                ?? $rawPayload['link']
                ?? $rawPayload['landing_url']
                ?? $rawPayload['page_url']
                ?? null
        );

        if ($primaryUrl && $incomingUrl && hash_equals($primaryUrl, $incomingUrl)) {
            return true;
        }

        return false;
    }

    protected function normalizeLandingUrl(mixed $url): ?string
    {
        if (! is_scalar($url)) {
            return null;
        }

        $raw = trim((string) $url);
        if ($raw === '') {
            return null;
        }

        $parts = parse_url($raw);
        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            $hostPath = preg_replace('#^https?://#i', '', $raw) ?? $raw;
            $hostPath = explode('?', $hostPath, 2)[0];

            return strtolower(rtrim($hostPath, '/'));
        }

        $host = strtolower((string) $parts['host']);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return $host.($path !== '' ? $path : '');
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    protected function forceUpsellItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $forced = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $item['item_type'] = 'upsell';
            $item['type'] = 'upsell';
            $item['origin'] = $item['origin'] ?? 'upsell';
            $forced[] = $item;
        }

        return $forced;
    }

    /**
     * Đơn gần đây cùng SĐT + cùng utm_source (đúng ý: một khách / một mã ads).
     */
    protected function findOrderByPhoneAndUtm(?string $phone, ?string $utmSource, MarketingSource $campaign): ?Order
    {
        if (! $phone || ! filled($utmSource)) {
            return null;
        }

        $windowDays = (int) config('saleops.lead_routing.duplicate_window_days', 30);

        $orderId = LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->where('utm_source', $utmSource)
            ->where('marketing_source_id', $campaign->id)
            ->whereNotNull('order_id')
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->latest('id')
            ->value('order_id');

        if (! $orderId) {
            $orderId = Order::query()
                ->where('customer_phone', $phone)
                ->where('marketing_source_id', $campaign->id)
                ->where('created_at', '>=', now()->subDays($windowDays))
                ->whereHas('leadPackets', fn ($q) => $q->where('utm_source', $utmSource))
                ->latest('id')
                ->value('id');
        }

        return $orderId ? Order::query()->find($orderId) : null;
    }

    /**
     * Tìm lead đang gom / chờ chia (CHƯA tạo đơn) của cùng khách để cộng dồn.
     */
    protected function findActiveGatheringLead(MarketingSource $campaign, string $phone, ?LandingSession $session): ?LeadIngestion
    {
        if ($session?->lead_ingestion_id) {
            $viaSession = LeadIngestion::query()
                ->whereKey($session->lead_ingestion_id)
                ->where('counts_as_lead', true)
                ->whereIn('status', [LeadIngestionStatus::Gathering, LeadIngestionStatus::Pending])
                ->whereNull('order_id')
                ->first();

            if ($viaSession) {
                return $viaSession;
            }
        }

        $windowMinutes = (int) config('saleops.landing.grouping_window_minutes', 15);

        return LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->where('marketing_source_id', $campaign->id)
            ->where('counts_as_lead', true)
            ->whereIn('status', [LeadIngestionStatus::Gathering, LeadIngestionStatus::Pending])
            ->whereNull('order_id')
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->latest('id')
            ->first();
    }

    /**
     * Tìm đơn vừa tạo (trong cửa sổ gom) để cộng dồn upsale.
     *
     * @param  array<string, mixed>  $normalized
     */
    protected function findRecentOrderForMerge(
        array $normalized,
        ?string $phone,
        MarketingSource $campaign,
        ?LandingSession $session = null,
    ): ?Order {
        // Cùng phiên JS: chỉ dùng khi đúng chiến dịch/SĐT và cửa sổ vẫn mở.
        if ($session?->order_id) {
            $viaSession = Order::query()->find($session->order_id);

            if ($this->isMergeCandidate($viaSession, $campaign, $phone)) {
                return $viaSession;
            }
        }

        // Tham chiếu tường minh do JS/Auto Funnel chuyển sang trang cảm ơn.
        $parentRef = $normalized['parent_ref'] ?? null;

        if (filled($parentRef)) {
            $byCode = Order::query()->where('order_code', $parentRef)->latest('id')->first();

            if ($this->isMergeCandidate($byCode, $campaign, $phone)) {
                return $byCode;
            }

            $viaLead = LeadIngestion::query()
                ->where(function ($q) use ($parentRef) {
                    $q->where('external_id', $parentRef)
                        ->orWhere('payload->client_ref', $parentRef);
                })
                ->whereNotNull('order_id')
                ->latest('id')
                ->first();

            if ($this->isMergeCandidate($viaLead?->order, $campaign, $phone)) {
                return $viaLead->order;
            }
        }

        if (! $phone) {
            return null;
        }

        // Fallback an toàn: cùng SĐT + cùng chiến dịch + hold còn hiệu lực.
        $candidate = Order::query()
            ->where('customer_phone', $phone)
            ->where('marketing_source_id', $campaign->id)
            ->where('landing_upsell_locked', false)
            ->whereNotNull('landing_upsell_hold_until')
            ->where('landing_upsell_hold_until', '>', now())
            ->latest('id')
            ->first();

        return $this->isMergeCandidate($candidate, $campaign, $phone)
            ? $candidate
            : null;
    }

    protected function isMergeCandidate(
        ?Order $order,
        MarketingSource $campaign,
        ?string $phone,
    ): bool {
        if (! $order || (int) $order->marketing_source_id !== (int) $campaign->id) {
            return false;
        }

        if ($phone && $order->customer_phone !== $phone) {
            return false;
        }

        return $this->landingUpsell->canMerge($order);
    }

    /**
     * Cộng dồn 1 gói tin vào lead đang gom (chưa tạo đơn): gộp item + chiết khấu
     * vào payload để khi CHỐT sẽ tạo đúng 1 đơn đủ hàng.
     *
     * @param  array<string, mixed>  $normalized
     */
    protected function mergePacketIntoLead(
        LeadPayloadNormalizer $driver,
        LeadIngestion $lead,
        MarketingSource $campaign,
        array $normalized,
        array $rawPayload,
        string $externalId,
        LeadPacketType $packetType,
        ?LandingSession $session,
        ?LeadIngestion $existingPacket = null,
    ): LeadIngestion {
        return DB::transaction(function () use (
            $driver,
            $lead,
            $campaign,
            $normalized,
            $rawPayload,
            $externalId,
            $packetType,
            $session,
            $existingPacket,
        ): LeadIngestion {
            $lead = LeadIngestion::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            /*
             * Khoá lead chính trước rồi mới kiểm tra idempotency. Hai worker nhận
             * cùng một submission sẽ tuần tự hoá tại đây; worker thứ hai nhìn
             * thấy packet đã lưu và tuyệt đối không cộng item/discount lần nữa.
             */
            if ($externalId !== '') {
                $duplicate = LeadIngestion::query()
                    ->where('platform', $driver->platform())
                    ->where('external_id', $externalId)
                    ->when($existingPacket, fn ($query) => $query->where('id', '!=', $existingPacket->id))
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }
            }

            $packet = $existingPacket
                ? LeadIngestion::query()->whereKey($existingPacket->id)->lockForUpdate()->firstOrFail()
                : new LeadIngestion;

            if (
                $packet->exists
                && $packet->status !== LeadIngestionStatus::Gathering
                && ! (
                    $packet->status === LeadIngestionStatus::NeedsReview
                    && $packet->packet_type === LeadPacketType::OrphanUpsell
                    && ! $packet->reviewed_at
                )
            ) {
                return $packet;
            }

            $payload = is_array($lead->payload) ? $lead->payload : [];
            $mergedIds = (array) ($payload['merged_ext_ids'] ?? []);

            if ($externalId !== '' && in_array($externalId, $mergedIds, true)) {
                return $packet->exists ? $packet : LeadIngestion::query()
                    ->where('platform', $driver->platform())
                    ->where('external_id', $externalId)
                    ->firstOrFail();
            }

            $incomingItems = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
            $existingItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $payload['items'] = array_merge($existingItems, $incomingItems);

            $extraDiscount = max(0, (int) ($normalized['discount'] ?? 0));
            if ($extraDiscount > 0) {
                $payload['discount'] = (int) ($payload['discount'] ?? 0) + $extraDiscount;
            }

            foreach (['shipping_address', 'shipping_notes', 'deposit', 'shipping_fee_collected'] as $key) {
                if (blank($payload[$key] ?? null) && filled($normalized[$key] ?? null)) {
                    $payload[$key] = $normalized[$key];
                }
            }

            // Tin nhắn khách là dữ liệu riêng; không trộn tên sản phẩm upsell vào note.
            if (blank($payload['message'] ?? null) && filled($normalized['message'] ?? null)) {
                $payload['message'] = $normalized['message'];
            }

            if ($externalId !== '') {
                $mergedIds[] = $externalId;
            }
            $payload['merged_ext_ids'] = array_values(array_unique($mergedIds));
            $lead->payload = $payload;

            if (blank($lead->customer_name) && filled($normalized['customer_name'] ?? null)) {
                $lead->customer_name = $normalized['customer_name'];
            }

            $lead->save();

            $packet->fill([
                'platform' => $driver->platform(),
                'external_id' => $externalId !== '' ? $externalId : uniqid('supp_', true),
                'status' => LeadIngestionStatus::Processed,
                'packet_type' => $packetType,
                'counts_as_lead' => false,
                'customer_name' => $normalized['customer_name'] ?? $lead->customer_name,
                'customer_phone' => $lead->customer_phone,
                'product_interest' => $normalized['product_interest'] ?? null,
                'utm_source' => $normalized['utm_source'] ?? $lead->utm_source,
                'utm_campaign' => $normalized['utm_campaign'] ?? $lead->utm_campaign,
                'marketing_source_id' => $campaign->id,
                'payload' => $this->storedPacketPayload($rawPayload, $normalized),
                'parent_ingestion_id' => $lead->id,
                'order_id' => null,
                'related_order_id' => null,
                'requires_review' => false,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'review_resolution' => null,
                'review_note' => null,
                'error_message' => null,
                'processed_at' => now(),
            ]);
            $packet->save();

            if ($session) {
                $session->forceFill([
                    'lead_ingestion_id' => $lead->id,
                    'last_activity_at' => now(),
                ])->save();
            }

            $this->pingMarketingDashboard($campaign);

            return $packet->refresh();
        });
    }

    /**
     * Cộng một packet bổ sung vào đơn đã tạo. Packet được lưu riêng để audit,
     * nhưng không được tính là lead mới và không chạy chia số lần nữa.
     *
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $rawPayload
     */
    protected function mergePacketIntoOrder(
        LeadPayloadNormalizer $driver,
        Order $order,
        MarketingSource $campaign,
        array $normalized,
        array $rawPayload,
        string $externalId,
        LeadPacketType $packetType,
        ?LandingSession $session,
        ?LeadIngestion $existingPacket = null,
    ): ?LeadIngestion {
        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        $extraDiscount = max(0, (int) ($normalized['discount'] ?? 0));

        return DB::transaction(function () use (
            $driver,
            $order,
            $campaign,
            $normalized,
            $rawPayload,
            $externalId,
            $packetType,
            $session,
            $existingPacket,
            $items,
            $extraDiscount,
        ): ?LeadIngestion {
            // Mọi mutation của một order đều cùng thứ tự lock: order -> packet.
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $order || ! $this->landingUpsell->canMerge($order)) {
                return null;
            }

            if ($externalId !== '') {
                $duplicate = LeadIngestion::query()
                    ->where('platform', $driver->platform())
                    ->where('external_id', $externalId)
                    ->when($existingPacket, fn ($query) => $query->where('id', '!=', $existingPacket->id))
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }
            }

            $packet = $existingPacket
                ? LeadIngestion::query()->whereKey($existingPacket->id)->lockForUpdate()->first()
                : new LeadIngestion;

            if (! $packet) {
                return null;
            }

            if (
                $packet->exists
                && $packet->status !== LeadIngestionStatus::Gathering
                && ! (
                    $packet->status === LeadIngestionStatus::NeedsReview
                    && $packet->packet_type === LeadPacketType::OrphanUpsell
                    && ! $packet->reviewed_at
                )
            ) {
                return $packet;
            }

            if ($items !== [] || $extraDiscount > 0) {
                $upsellItems = array_map(static function ($item) {
                    if (! is_array($item)) {
                        return $item;
                    }
                    $item['item_type'] = 'upsell';
                    $item['type'] = 'upsell';
                    $item['origin'] = $item['origin'] ?? 'upsell';

                    return $item;
                }, $items);
                $this->orderFactory->appendItems($order, $upsellItems, $extraDiscount, 'upsell');
            }

            $parent = $this->findPrimaryIngestionForOrder($order);
            $packet->fill([
                'platform' => $driver->platform(),
                'external_id' => $externalId !== '' ? $externalId : uniqid('supp_', true),
                'status' => LeadIngestionStatus::Processed,
                'packet_type' => $packetType,
                'counts_as_lead' => false,
                'customer_name' => $normalized['customer_name'] ?? $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'product_interest' => $normalized['product_interest'] ?? null,
                'utm_source' => $normalized['utm_source'] ?? $parent?->utm_source,
                'utm_campaign' => $normalized['utm_campaign'] ?? $parent?->utm_campaign,
                'marketing_source_id' => $campaign->id,
                'payload' => $this->storedPacketPayload($rawPayload, $normalized),
                'order_id' => $order->id,
                'related_order_id' => null,
                'parent_ingestion_id' => $parent?->id,
                'requires_review' => false,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'review_resolution' => null,
                'review_note' => null,
                'error_message' => null,
                'processed_at' => now(),
            ]);
            $packet->save();

            if ($session) {
                $session->forceFill([
                    'order_id' => $order->id,
                    'lead_ingestion_id' => $parent?->id ?? $session->lead_ingestion_id,
                    'last_activity_at' => now(),
                ])->save();
            }

            $this->recordLandingPacketHistory($order, $packet, $items, late: false);

            if ($order->sale_user_id) {
                $this->broadcastSafe(new SaleWorkspaceChanged($order->sale_user_id));
                NotificationService::push($order->sale_user_id, 'lead', null, null, '/sales/workspace', [
                    'variant' => 'upsell',
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'order_code' => $order->order_code,
                ]);
            }

            ActivityLogger::log(
                ActivityLogger::LEAD_INGESTED,
                $packet,
                [
                    'order_id' => $order->id,
                    'campaign_id' => $campaign->id,
                    'customer_phone' => $order->customer_phone,
                    'packet_type' => $packetType->value,
                    'counts_as_lead' => false,
                    'order_total' => $order->fresh()->total,
                ],
                ($normalized['customer_name'] ?? $order->customer_name).' — '.$packetType->label(),
            );

            $this->pingMarketingDashboard($campaign);

            return $packet->refresh();
        });
    }

    /** @param array<string, mixed> $normalized */
    protected function findRelatedOrderOutsideMergeWindow(
        array $normalized,
        ?string $phone,
        MarketingSource $campaign,
        ?LandingSession $session,
        bool $allowPhoneFallback = true,
    ): ?Order {
        $candidates = collect();

        if ($session?->order_id) {
            $candidates->push(Order::query()->find($session->order_id));
        }

        $parentRef = $normalized['parent_ref'] ?? null;
        if (filled($parentRef)) {
            $candidates->push(Order::query()->where('order_code', $parentRef)->latest('id')->first());

            $lead = LeadIngestion::query()
                ->where(function ($query) use ($parentRef): void {
                    $query->where('external_id', $parentRef)
                        ->orWhere('payload->client_ref', $parentRef);
                })
                ->where(function ($query): void {
                    $query->whereNotNull('order_id')->orWhereNotNull('related_order_id');
                })
                ->latest('id')
                ->first();
            $candidates->push($lead?->effectiveOrder());
        }

        if ($phone && $allowPhoneFallback) {
            $windowDays = (int) config('saleops.lead_routing.duplicate_window_days', 30);
            $candidates->push(Order::query()
                ->where('customer_phone', $phone)
                ->where('marketing_source_id', $campaign->id)
                ->where('created_at', '>=', now()->subDays($windowDays))
                ->latest('id')
                ->first());
        }

        return $candidates
            ->filter()
            ->first(fn (Order $order): bool =>
                (int) $order->marketing_source_id === (int) $campaign->id
                && (! $phone || $order->customer_phone === $phone)
            );
    }

    /**
     * Packet bổ sung đến sau cửa sổ 90 giây: không tạo đơn mới, không chia số lại.
     * Giữ liên kết đến đơn gốc và đưa vào hàng chờ kiểm tra của sale/allocator/admin.
     *
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $rawPayload
     */
    protected function recordLateSupplementPacket(
        LeadPayloadNormalizer $driver,
        Order $relatedOrder,
        MarketingSource $campaign,
        array $normalized,
        array $rawPayload,
        string $externalId,
        ?LandingSession $session,
        ?LeadIngestion $existingPacket = null,
    ): LeadIngestion {
        return DB::transaction(function () use (
            $driver,
            $relatedOrder,
            $campaign,
            $normalized,
            $rawPayload,
            $externalId,
            $session,
            $existingPacket,
        ): LeadIngestion {
            $relatedOrder = Order::query()->whereKey($relatedOrder->id)->lockForUpdate()->firstOrFail();

            if ($externalId !== '') {
                $duplicate = LeadIngestion::query()
                    ->where('platform', $driver->platform())
                    ->where('external_id', $externalId)
                    ->when($existingPacket, fn ($query) => $query->where('id', '!=', $existingPacket->id))
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }
            }

            $packet = $existingPacket
                ? LeadIngestion::query()->whereKey($existingPacket->id)->lockForUpdate()->firstOrFail()
                : new LeadIngestion;

            // Một retry sau khi người dùng đã xử lý không được mở lại ngoại lệ.
            if ($packet->exists && $packet->reviewed_at) {
                return $packet;
            }

            $wasAlreadyReview = $packet->exists
                && $packet->status === LeadIngestionStatus::NeedsReview
                && (int) $packet->related_order_id === (int) $relatedOrder->id;

            $parent = $this->findPrimaryIngestionForOrder($relatedOrder);
            $payload = $this->storedPacketPayload($rawPayload, $normalized);
            $payload['conflict_order_id'] = $relatedOrder->id;
            $payload['conflict_order_code'] = $relatedOrder->order_code;

            $packet->fill([
                'platform' => $driver->platform(),
                'external_id' => $externalId !== '' ? $externalId : uniqid('late_up_', true),
                'status' => LeadIngestionStatus::NeedsReview,
                'packet_type' => LeadPacketType::LateUpsell,
                'counts_as_lead' => false,
                'customer_name' => $normalized['customer_name'] ?? $relatedOrder->customer_name,
                'customer_phone' => $relatedOrder->customer_phone,
                'product_interest' => $normalized['product_interest'] ?? null,
                'utm_source' => $normalized['utm_source'] ?? $parent?->utm_source,
                'utm_campaign' => $normalized['utm_campaign'] ?? $parent?->utm_campaign,
                'marketing_source_id' => $campaign->id,
                'payload' => $payload,
                'parent_ingestion_id' => $parent?->id,
                'order_id' => null,
                'related_order_id' => $relatedOrder->id,
                'requires_review' => true,
                'error_message' => __('messages.lead_intake.late_upsell_review', [
                    'code' => $relatedOrder->order_code,
                    'seconds' => $this->landingUpsell->maxHoldSeconds(),
                ]),
                'processed_at' => now(),
            ]);
            $packet->save();

            if ($session) {
                $session->forceFill([
                    'order_id' => $relatedOrder->id,
                    'lead_ingestion_id' => $parent?->id ?? $session->lead_ingestion_id,
                    'last_activity_at' => now(),
                ])->save();
            }

            if (! $wasAlreadyReview) {
                $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
                $this->recordLandingPacketHistory($relatedOrder, $packet, $items, late: true);
                $this->notifySupplementReview($packet, $relatedOrder);
                $this->pingMarketingDashboard($campaign);
            }

            return $packet->refresh();
        });
    }

    /**
     * Packet upsell có thể đến trước form chính. Lưu tạm và retry trong cửa sổ
     * 90 giây; tuyệt đối không dùng packet này để tạo/chia một đơn độc lập.
     *
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $rawPayload
     */
    protected function recordPendingSupplementPacket(
        LeadPayloadNormalizer $driver,
        MarketingSource $campaign,
        array $normalized,
        array $rawPayload,
        string $externalId,
        LeadPacketType $packetType,
        ?LandingSession $session,
    ): LeadIngestion {
        $externalId = $externalId !== '' ? $externalId : uniqid('pending_up_', true);

        $packet = LeadIngestion::query()->firstOrCreate(
            [
                'platform' => $driver->platform(),
                'external_id' => $externalId,
            ],
            [
                'status' => LeadIngestionStatus::Gathering,
                'packet_type' => $packetType,
                'counts_as_lead' => false,
                'customer_name' => $normalized['customer_name'] ?? null,
                'customer_phone' => $normalized['customer_phone'] ?? null,
                'product_interest' => $normalized['product_interest'] ?? null,
                'utm_source' => $normalized['utm_source'] ?? null,
                'utm_campaign' => $normalized['utm_campaign'] ?? null,
                'marketing_source_id' => $campaign->id,
                'payload' => $this->storedPacketPayload($rawPayload, $normalized),
                'parent_ingestion_id' => $session?->lead_ingestion_id,
            ],
        );

        if ($packet->wasRecentlyCreated) {
            try {
                $pending = FinalizeLandingSupplementPacketJob::dispatch($packet->id, $campaign->company_id)
                    ->delay(now()->addSeconds(5));
                unset($pending);
            } catch (\Throwable $e) {
                Log::warning('FinalizeLandingSupplementPacketJob dispatch failed (lead intake continues)', [
                    'packet_id' => $packet->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $packet;
    }

    public function resolvePendingSupplementPacket(LeadIngestion $packet): void
    {
        $packet = LeadIngestion::query()->whereKey($packet->id)->first();
        if (! $packet || $packet->status !== LeadIngestionStatus::Gathering || $packet->counts_as_lead) {
            return;
        }

        $campaign = $packet->marketingSource;
        if (! $campaign) {
            $this->markOrphanSupplementForReview($packet);
            return;
        }

        $payload = is_array($packet->payload) ? $packet->payload : [];
        $normalized = $this->normalizedFromStoredPacket($packet);
        $session = $this->resolveSession($campaign, $payload, $packet->customer_phone);
        $driver = IntegrationDriverFactory::make('landing');

        if ($packet->customer_phone && ($base = $this->findActiveGatheringLead($campaign, $packet->customer_phone, $session))) {
            $this->mergePacketIntoLead(
                $driver,
                $base,
                $campaign,
                $normalized,
                $payload,
                (string) $packet->external_id,
                $packet->packet_type ?? LeadPacketType::Upsell,
                $session,
                $packet,
            );
            return;
        }

        if ($order = $this->findRecentOrderForMerge($normalized, $packet->customer_phone, $campaign, $session)) {
            $this->mergePacketIntoOrder(
                $driver,
                $order,
                $campaign,
                $normalized,
                $payload,
                (string) $packet->external_id,
                $packet->packet_type ?? LeadPacketType::Upsell,
                $session,
                $packet,
            );
            return;
        }

        /*
         * Ref/session tường minh có thể liên kết ngay. Riêng fallback bằng SĐT
         * phải chờ hết cửa sổ: packet upsell đôi khi được worker xử lý trước form
         * chính; nối ngay theo phone có thể nhầm vào đơn cũ của khách.
         */
        if ($relatedOrder = $this->findRelatedOrderOutsideMergeWindow(
            $normalized,
            $packet->customer_phone,
            $campaign,
            $session,
            allowPhoneFallback: false,
        )) {
            $this->recordLateSupplementPacket(
                $driver,
                $relatedOrder,
                $campaign,
                $normalized,
                $payload,
                (string) $packet->external_id,
                $session,
                $packet,
            );
            return;
        }

        $age = $packet->created_at?->diffInSeconds(now()) ?? $this->landingUpsell->maxHoldSeconds();
        if ($age < $this->landingUpsell->maxHoldSeconds()) {
            FinalizeLandingSupplementPacketJob::dispatch($packet->id, $campaign->company_id)
                ->delay(now()->addSeconds(max(1, min(5, $this->landingUpsell->maxHoldSeconds() - $age))));
            return;
        }

        if ($relatedOrder = $this->findRelatedOrderOutsideMergeWindow(
            $normalized,
            $packet->customer_phone,
            $campaign,
            $session,
            allowPhoneFallback: true,
        )) {
            $this->recordLateSupplementPacket(
                $driver,
                $relatedOrder,
                $campaign,
                $normalized,
                $payload,
                (string) $packet->external_id,
                $session,
                $packet,
            );
            return;
        }

        $this->markOrphanSupplementForReview($packet);
    }

    protected function markOrphanSupplementForReview(LeadIngestion $packet): void
    {
        $transitioned = DB::transaction(function () use ($packet): bool {
            $packet = LeadIngestion::query()
                ->whereKey($packet->id)
                ->lockForUpdate()
                ->first();

            if (! $packet || $packet->reviewed_at) {
                return false;
            }

            // Retry/concurrent worker chỉ được phát cảnh báo đúng một lần.
            if (
                $packet->status === LeadIngestionStatus::NeedsReview
                && $packet->packet_type === LeadPacketType::OrphanUpsell
                && $packet->requires_review
            ) {
                return false;
            }

            if ($packet->status !== LeadIngestionStatus::Gathering) {
                return false;
            }

            $packet->forceFill([
                'status' => LeadIngestionStatus::NeedsReview,
                'packet_type' => LeadPacketType::OrphanUpsell,
                'counts_as_lead' => false,
                'requires_review' => true,
                'error_message' => __('messages.lead_intake.orphan_upsell_review'),
                'processed_at' => now(),
            ])->save();

            return true;
        });

        if (! $transitioned) {
            return;
        }

        $packet->refresh();

        NotificationService::pushToRole(UserRole::Admin, 'lead', null, null, '/admin/leads/log?bucket=exceptions', [
            'variant' => 'orphan_upsell',
            'customer_name' => $packet->customer_name,
            'customer_phone' => $packet->customer_phone,
        ]);
        NotificationService::pushToRole(UserRole::Allocator, 'lead', null, null, '/allocator/leads/log?bucket=exceptions', [
            'variant' => 'orphan_upsell',
            'customer_name' => $packet->customer_name,
            'customer_phone' => $packet->customer_phone,
        ]);
    }

    protected function findPrimaryIngestionForOrder(Order $order): ?LeadIngestion
    {
        return LeadIngestion::query()
            ->where('order_id', $order->id)
            ->where('counts_as_lead', true)
            ->oldest('created_at')
            ->oldest('id')
            ->first()
            ?? LeadIngestion::query()->where('order_id', $order->id)->oldest('id')->first();
    }

    /** @param array<string, mixed> $rawPayload @param array<string, mixed> $normalized */
    protected function storedPacketPayload(array $rawPayload, array $normalized): array
    {
        $payload = $rawPayload;
        foreach (['items', 'discount', 'shipping_address', 'shipping_notes', 'deposit', 'shipping_fee_collected', 'message'] as $key) {
            if (array_key_exists($key, $normalized) && $normalized[$key] !== null && $normalized[$key] !== '') {
                $payload[$key] = $normalized[$key];
            }
        }

        if ($clientRef = $this->extractClientRef($rawPayload)) {
            $payload['client_ref'] = $clientRef;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    protected function normalizedFromStoredPacket(LeadIngestion $packet): array
    {
        $payload = is_array($packet->payload) ? $packet->payload : [];

        return [
            'external_id' => $packet->external_id,
            'customer_name' => $packet->customer_name,
            'customer_phone' => $packet->customer_phone,
            'product_interest' => $packet->product_interest,
            'utm_source' => $packet->utm_source,
            'utm_campaign' => $packet->utm_campaign,
            'parent_ref' => $payload['parent_submission_id'] ?? $payload['parent_ref'] ?? $payload['client_ref'] ?? null,
            'items' => is_array($payload['items'] ?? null) ? $payload['items'] : [],
            'discount' => (int) ($payload['discount'] ?? 0),
            'shipping_address' => $payload['shipping_address'] ?? null,
            'shipping_notes' => $payload['shipping_notes'] ?? null,
            'deposit' => $payload['deposit'] ?? null,
            'shipping_fee_collected' => $payload['shipping_fee_collected'] ?? null,
            'message' => $payload['message'] ?? null,
        ];
    }

    /** @param array<string, mixed> $rawPayload @param array<string, mixed> $normalized */
    protected function looksLikeSupplementalPacket(array $rawPayload, array $normalized): bool
    {
        $flag = Arr::get($rawPayload, 'is_upsell') ?? Arr::get($rawPayload, 'fields.is_upsell');
        if (in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (strtolower((string) (Arr::get($rawPayload, 'item_type') ?? '')) === 'upsell') {
            return true;
        }

        foreach (array_keys($rawPayload) as $key) {
            if (preg_match('/^(mua_them|upsell|addon)_/i', (string) $key)) {
                return true;
            }
        }

        foreach ((array) ($normalized['items'] ?? []) as $item) {
            if (is_array($item) && strtolower((string) ($item['item_type'] ?? '')) === 'upsell') {
                return true;
            }
        }

        return false;
    }

    protected function packetExternalId(string $externalId, LeadPacketType $packetType): string
    {
        if ($externalId === '') {
            return '';
        }

        $suffix = match ($packetType) {
            LeadPacketType::Upsell => ':upsell',
            LeadPacketType::FollowUp => ':follow_up',
            default => '',
        };

        return $suffix !== '' && ! str_ends_with($externalId, $suffix)
            ? $externalId.$suffix
            : $externalId;
    }

    /** @param list<array<string, mixed>> $items */
    protected function recordLandingPacketHistory(Order $order, LeadIngestion $packet, array $items, bool $late): void
    {
        OrderOperationHistory::query()->create([
            'company_id' => $order->company_id,
            'order_id' => $order->id,
            'actor_user_id' => null,
            'actor_name' => __('operations.customer_interactions.system_actor'),
            'actor_role' => null,
            'action' => $late ? 'landing_upsell_requires_review' : 'landing_upsell_added',
            'operation_stage_before' => $order->operation_stage,
            'operation_stage_after' => $order->operation_stage,
            'operation_result' => $order->operation_result,
            'next_operation_at' => $order->next_operation_at,
            'note' => $late
                ? __('messages.lead_intake.late_upsell_history')
                : __('messages.lead_intake.upsell_merged_history'),
            'metadata' => [
                'lead_ingestion_id' => $packet->id,
                'packet_type' => $packet->packet_type?->value,
                'items' => collect($items)->map(fn (array $item): array => [
                    'name' => $item['product_name'] ?? $item['name'] ?? null,
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'unit_price' => (int) ($item['unit_price'] ?? 0),
                ])->values()->all(),
            ],
            'created_at' => now(),
        ]);
    }

    protected function notifyDuplicateLeadReview(LeadIngestion $lead, Order $order): void
    {
        $data = [
            'variant' => 'duplicate_lead',
            'customer_name' => $lead->customer_name,
            'customer_phone' => $lead->customer_phone,
            'order_code' => $order->order_code,
            'lead_ingestion_id' => $lead->id,
        ];

        if ($order->sale_user_id) {
            NotificationService::push($order->sale_user_id, 'lead', null, null, '/sales/customers?search='.$order->customer_phone, $data);
        }
        NotificationService::pushToRole(UserRole::Admin, 'lead', null, null, '/admin/leads/log?bucket=exceptions', $data);
        NotificationService::pushToRole(UserRole::Allocator, 'lead', null, null, '/allocator/leads/log?bucket=exceptions', $data);
    }

    protected function notifySupplementReview(LeadIngestion $packet, Order $order): void
    {
        $data = [
            'variant' => 'late_upsell',
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'order_code' => $order->order_code,
            'lead_ingestion_id' => $packet->id,
        ];

        if ($order->sale_user_id) {
            NotificationService::push($order->sale_user_id, 'lead', null, null, '/sales/customers?search='.$order->customer_phone, $data);
        }
        NotificationService::pushToRole(UserRole::Admin, 'lead', null, null, '/admin/leads/log?bucket=exceptions', $data);
        NotificationService::pushToRole(UserRole::Allocator, 'lead', null, null, '/allocator/leads/log?bucket=exceptions', $data);
    }

    /**
     * Đồng bộ mọi packet bổ sung tới trước/sau packet chính khi order vừa được tạo.
     * Cửa sổ 90 giây luôn neo vào thời điểm lead đầu tiên về, không neo vào lúc
     * allocator bấm chia nên việc chia tay muộn không thể mở lại cửa sổ gộp.
     */
    public function reconcileLandingOrder(
        LeadIngestion $baseLead,
        Order $order,
        MarketingSource $campaign,
        ?LandingSession $session = null,
    ): void {
        $baseLead->refresh();
        $order->refresh();

        $session ??= LandingSession::query()
            ->where(function ($query) use ($baseLead, $order): void {
                $query->where('lead_ingestion_id', $baseLead->id)
                    ->orWhere('order_id', $order->id);
            })
            ->latest('id')
            ->first();

        $holdOpen = $this->landingUpsell->startHold($order, $order->created_at ?? now());
        $order->refresh();
        $driver = IntegrationDriverFactory::make('landing');
        $margin = 15;
        $from = ($baseLead->created_at ?? now())
            ->copy()
            ->subSeconds($margin);
        $to = ($baseLead->created_at ?? now())
            ->copy()
            ->addSeconds($this->landingUpsell->maxHoldSeconds() + $margin);

        $packets = LeadIngestion::query()
            ->where('marketing_source_id', $campaign->id)
            ->where('counts_as_lead', false)
            ->whereNull('order_id')
            ->whereNull('reviewed_at')
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query): void {
                $query->where('status', LeadIngestionStatus::Gathering)
                    ->orWhere(function ($orphan): void {
                        $orphan->where('status', LeadIngestionStatus::NeedsReview)
                            ->where('packet_type', LeadPacketType::OrphanUpsell)
                            ->whereNull('related_order_id');
                    });
            })
            ->oldest('created_at')
            ->oldest('id')
            ->limit(100)
            ->get();

        foreach ($packets as $packet) {
            if (! $this->pendingPacketMatchesBase($packet, $baseLead, $order, $session)) {
                continue;
            }

            $normalized = $this->normalizedFromStoredPacket($packet);
            $rawPayload = is_array($packet->payload) ? $packet->payload : [];

            if ($holdOpen && $this->landingUpsell->canMerge($order->fresh())) {
                $merged = $this->mergePacketIntoOrder(
                    $driver,
                    $order,
                    $campaign,
                    $normalized,
                    $rawPayload,
                    (string) $packet->external_id,
                    $packet->packet_type ?? LeadPacketType::Upsell,
                    $session,
                    $packet,
                );

                if ($merged?->order_id) {
                    continue;
                }
            }

            $this->recordLateSupplementPacket(
                $driver,
                $order,
                $campaign,
                $normalized,
                $rawPayload,
                (string) $packet->external_id,
                $session,
                $packet,
            );
        }

        if ($holdOpen && $order->landing_upsell_hold_until) {
            try {
                $pending = FinalizeLandingLeadJob::dispatch($baseLead->id, $campaign->company_id)
                    ->delay($order->landing_upsell_hold_until);
                // Ép push ngay trong try — tránh __destruct ném Redis error ra ngoài luồng ingest.
                unset($pending);
            } catch (\Throwable $e) {
                Log::warning('FinalizeLandingLeadJob dispatch failed (lead intake continues)', [
                    'lead_id' => $baseLead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function pendingPacketMatchesBase(
        LeadIngestion $packet,
        LeadIngestion $baseLead,
        Order $order,
        ?LandingSession $session,
    ): bool {
        if ($packet->parent_ingestion_id && (int) $packet->parent_ingestion_id === (int) $baseLead->id) {
            return true;
        }

        if ($packet->customer_phone && $packet->customer_phone === $order->customer_phone) {
            return true;
        }

        $packetPayload = is_array($packet->payload) ? $packet->payload : [];
        $basePayload = is_array($baseLead->payload) ? $baseLead->payload : [];
        $packetSession = $this->extractSessionKey($packetPayload);

        if ($session && $packetSession && hash_equals((string) $session->session_key, $packetSession)) {
            return true;
        }

        $baseRefs = array_filter([
            $baseLead->external_id,
            $basePayload['client_ref'] ?? null,
            $this->extractClientRef($basePayload),
        ]);
        $packetRefs = array_filter([
            $packetPayload['parent_submission_id'] ?? null,
            $packetPayload['parent_ref'] ?? null,
            $packetPayload['client_ref'] ?? null,
        ]);

        return array_intersect(array_map('strval', $baseRefs), array_map('strval', $packetRefs)) !== [];
    }

    /**
     * Phiên Landing (JS) — tra theo session_key trong payload; tạo mới nếu chưa có.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    protected function resolveSession(MarketingSource $campaign, array $rawPayload, ?string $phone): ?LandingSession
    {
        $key = $this->extractSessionKey($rawPayload);

        if ($key === null) {
            return null;
        }

        $session = LandingSession::query()->firstOrCreate(
            ['session_key' => $key],
            [
                'company_id' => $campaign->company_id,
                'marketing_source_id' => $campaign->id,
                'customer_phone' => $phone,
                'status' => LandingSession::STATUS_OPEN,
                'last_activity_at' => now(),
            ],
        );

        if (! $session->wasRecentlyCreated) {
            $session->forceFill([
                'customer_phone' => $session->customer_phone ?: $phone,
                'marketing_source_id' => $session->marketing_source_id ?: $campaign->id,
                'last_activity_at' => now(),
            ])->save();
        }

        return $session;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    protected function extractSessionKey(array $rawPayload): ?string
    {
        $key = Arr::get($rawPayload, 'session_id')
            ?? Arr::get($rawPayload, 'session_key')
            ?? Arr::get($rawPayload, 'saleops_session')
            ?? Arr::get($rawPayload, 'fields.session_id');

        if (! is_scalar($key)) {
            return null;
        }

        $key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key) ?? '';

        return $key !== '' ? substr($key, 0, 64) : null;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingest(LeadPayloadNormalizer $driver, array $rawPayload): LeadIngestion
    {
        $normalized = $driver->normalize($rawPayload);
        $campaign = $this->resolveExternalCampaign($driver->platform(), $normalized);

        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign);
    }

    /**
     * Nền tảng ngoài không đi qua landing connection vẫn cần map về chiến dịch nội bộ
     * để báo cáo Marketing và quyền dữ liệu chạy đúng. Menu 1.11 quản lý mapping
     * Facebook PageID → Marketing, vì vậy Facebook lead được tự động gom vào
     * MarketingSource theo PageID.
     *
     * @param  array<string, mixed>  $normalized
     */
    private function resolveExternalCampaign(string $platform, array $normalized): ?MarketingSource
    {
        if ($platform !== IntegrationPlatform::Facebook->value) {
            return null;
        }

        $pageId = trim((string) ($normalized['facebook_page_id'] ?? ''));
        $campaignKey = $pageId !== '' ? $pageId : trim((string) ($normalized['utm_campaign'] ?? ''));

        if ($campaignKey === '') {
            return null;
        }

        $marketerId = filled($normalized['marketer_user_id'] ?? null) ? (int) $normalized['marketer_user_id'] : null;
        $name = 'Facebook — '.trim((string) ($normalized['facebook_page_name'] ?? $campaignKey));
        $source = MarketingSource::query()
            ->where('utm_source', 'facebook')
            ->where('utm_campaign', $campaignKey)
            ->first();

        if ($source) {
            $updates = [];
            if ($marketerId && ! $source->marketer_user_id) {
                $updates['marketer_user_id'] = $marketerId;
            }
            if (! $source->ad_channel) {
                $updates['ad_channel'] = 'Facebook';
            }
            if (! $source->is_active) {
                $updates['is_active'] = true;
            }
            if (! $source->is_approved) {
                $updates['is_approved'] = true;
            }
            if ($updates !== []) {
                $source->forceFill($updates)->save();
            }

            return $source->fresh();
        }

        return MarketingSource::query()->create([
            'name' => $name,
            'marketer_user_id' => $marketerId,
            'ad_channel' => 'Facebook',
            'utm_source' => 'facebook',
            'utm_campaign' => $campaignKey,
            'budget' => 0,
            'is_active' => true,
            'is_approved' => true,
        ]);
    }

    /**
     * Nhập lead THỦ CÔNG (form lẻ / CSV).
     * - $forceSale = null → chia theo cấu hình mặc định của hệ thống (auto route hoặc vào pool).
     * - $forceSale != null → CHIA THỦ CÔNG: gán thẳng cho sale được chọn, bỏ qua auto route.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingestManual(LeadPayloadNormalizer $driver, array $rawPayload, ?User $forceSale = null): LeadIngestion
    {
        $normalized = $driver->normalize($rawPayload);

        $campaign = null;
        if (! empty($rawPayload['marketing_source_id'])) {
            $campaign = MarketingSource::query()->find((int) $rawPayload['marketing_source_id']);
        }

        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign, null, $forceSale);
    }

    /**
     * Import từ nền tảng ngoài nhưng cần ép chia cho một sale cụ thể
     * (ví dụ Pancake Extension đang đăng nhập bằng tài khoản SaleOps của sale).
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingestWithForceSale(
        LeadPayloadNormalizer $driver,
        array $rawPayload,
        ?User $forceSale = null,
        ?MarketingSource $campaign = null,
        bool $forcePending = false,
    ): LeadIngestion {
        $normalized = $driver->normalize($rawPayload);

        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign, null, $forceSale, $forcePending);
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, mixed>  $normalized
     */
    protected function ingestNormalized(
        LeadPayloadNormalizer $driver,
        array $rawPayload,
        array $normalized,
        ?MarketingSource $campaign = null,
        ?LandingSession $session = null,
        ?User $forceSale = null,
        bool $forcePending = false,
    ): LeadIngestion {
        if ($this->sanitizer->exceedsPayloadLimit($rawPayload)) {
            return $this->recordFailed($driver->platform(), ['oversized' => true], __('messages.lead_intake.payload_too_large'));
        }

        if ($this->sanitizer->hasHoneypot($rawPayload)) {
            return $this->recordFailed($driver->platform(), $rawPayload, __('messages.lead_intake.honeypot'));
        }

        $phone = $this->sanitizer->normalizePhone($normalized['customer_phone'] ?? null);

        if (! $phone) {
            return $this->recordFailed($driver->platform(), $rawPayload, __('messages.lead_intake.invalid_phone'));
        }

        $normalized['customer_phone'] = $phone;

        $normalized['customer_name'] = $this->sanitizer->cleanText(
            $normalized['customer_name'] ?? null,
            (int) config('saleops.lead_intake.max_name_length', 100),
        ) ?? __('messages.lead_intake.guest_name');

        $normalized['product_interest'] = $this->sanitizer->cleanText(
            $normalized['product_interest'] ?? null,
            (int) config('saleops.lead_intake.max_product_length', 255),
        );

        if (array_key_exists('message', $normalized)) {
            $normalized['message'] = $this->sanitizer->cleanText(
                $normalized['message'],
                (int) config('saleops.lead_intake.max_message_length', 1000),
            );
        }

        if (array_key_exists('shipping_address', $normalized)) {
            $normalized['shipping_address'] = $this->sanitizer->cleanText(
                $normalized['shipping_address'],
                (int) config('saleops.lead_intake.max_address_length', 255),
            );
        }

        if ($this->sanitizer->looksLikeSpam($normalized['customer_name'], $normalized['message'] ?? null)) {
            return $this->recordFailed($driver->platform(), $rawPayload, __('messages.lead_intake.spam_detected'));
        }

        $existing = LeadIngestion::query()
            ->where('platform', $driver->platform())
            ->where('external_id', $normalized['external_id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $windowDays = (int) config('saleops.lead_routing.duplicate_window_days', 30);

        $duplicateOrderModel = $this->duplicatePolicy->findDuplicateOrder(
            $normalized['customer_phone'],
            $campaign,
            $windowDays,
        );
        $duplicateOrder = $this->duplicatePolicy->countsAsDuplicate($duplicateOrderModel);
        $duplicatePrimary = $duplicateOrderModel ? $this->findPrimaryIngestionForOrder($duplicateOrderModel) : null;

        // Luồng Landing: tạo/chia đơn ngay. Job chỉ đóng trạng thái mở gộp khi
        // hết 90 giây; tuyệt đối không trì hoãn tạo đơn hay phân sale.
        $hold = $campaign !== null && ! $duplicateOrder;

        $status = $duplicateOrder
            ? LeadIngestionStatus::Duplicate
            : LeadIngestionStatus::Pending;

        // Case ngoại lệ không thể tự xử lý (trùng số / upsale muộn quá cửa sổ gộp):
        // lưu lý do + đơn liên quan để bộ phận vận hành kiểm soát & xử lý tay.
        $exceptionReason = $duplicateOrder
            ? __('messages.lead_intake.duplicate_reason', [
                'code' => $duplicateOrderModel->order_code,
                'days' => $windowDays,
            ])
            : null;

        // Lead lưu kèm item/chiết khấu/địa chỉ ĐÃ chuẩn hoá vào payload để tự chứa đủ
        // dữ liệu — khi gộp thêm gói sau, khi chốt đơn, hoặc khi chia tay từ pool (lead
        // nhập tay/CSV) không bị mất hàng.
        $storedPayload = $rawPayload;
        $landingConnection = $campaign?->relationLoaded('landingConnection')
            ? $campaign->landingConnection
            : $campaign?->landingConnection()->first();
        $landingConnectionId = $landingConnection?->id
            ?? (filled($normalized['landing_connection_id'] ?? null) ? (int) $normalized['landing_connection_id'] : null);
        $landingConnectionSourceId = filled($normalized['landing_connection_source_id'] ?? null)
            ? (int) $normalized['landing_connection_source_id']
            : null;
        $normalizedItems = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        if ($campaign || $normalizedItems !== []) {
            $storedPayload['items'] = $normalizedItems;
        }
        if ($landingConnectionId) {
            $storedPayload['landing_connection_id'] = $landingConnectionId;
        }
        if ($landingConnectionSourceId) {
            $storedPayload['landing_connection_source_id'] = $landingConnectionSourceId;
        }
        if ((int) ($normalized['discount'] ?? 0) > 0) {
            $storedPayload['discount'] = (int) $normalized['discount'];
        }
        foreach (['facebook_page_id', 'facebook_page_name', 'facebook_creator_name', 'facebook_ad_id', 'marketer_user_id'] as $normalizedKey) {
            if (array_key_exists($normalizedKey, $normalized) && filled($normalized[$normalizedKey])) {
                $storedPayload[$normalizedKey] = $normalized[$normalizedKey];
            }
        }
        if (filled($normalized['shipping_address'] ?? null)) {
            $storedPayload['shipping_address'] = $normalized['shipping_address'];
        }
        foreach (['shipping_notes', 'deposit', 'shipping_fee_collected', 'item_origin'] as $normalizedKey) {
            if (array_key_exists($normalizedKey, $normalized) && filled($normalized[$normalizedKey])) {
                $storedPayload[$normalizedKey] = $normalized[$normalizedKey];
            }
        }
        $clientRef = $this->extractClientRef($rawPayload);
        if ($clientRef) {
            $storedPayload['client_ref'] = $clientRef;
        }

        if ($duplicateOrder) {
            $storedPayload['conflict_order_code'] = $duplicateOrderModel->order_code;
            $storedPayload['conflict_order_id'] = $duplicateOrderModel->id;
        }

        $ingestion = LeadIngestion::query()->firstOrCreate(
            [
                'platform' => $driver->platform(),
                'external_id' => $normalized['external_id'],
            ],
            [
                'status' => $status,
                'packet_type' => LeadPacketType::Lead,
                'counts_as_lead' => ! $duplicateOrder,
                'customer_name' => $normalized['customer_name'],
                'customer_phone' => $normalized['customer_phone'],
                'product_interest' => $normalized['product_interest'],
                'utm_source' => $normalized['utm_source'],
                'utm_campaign' => $normalized['utm_campaign'],
                'marketing_source_id' => $campaign?->id,
                'landing_connection_id' => $landingConnectionId,
                'landing_connection_source_id' => $landingConnectionSourceId,
                'payload' => $storedPayload,
                'parent_ingestion_id' => $duplicatePrimary?->id,
                'related_order_id' => $duplicateOrderModel?->id,
                'requires_review' => $duplicateOrder,
                'error_message' => $exceptionReason,
            ],
        );

        // firstOrCreate tự xử lý race unique key; retry không chạy lại contacts,
        // routing, notification hoặc tạo order lần hai.
        if (! $ingestion->wasRecentlyCreated) {
            return $ingestion;
        }

        if ($campaign && ! $duplicateOrder) {
            MarketingSource::query()->whereKey($campaign->id)->increment('contacts');
        }

        if ($duplicateOrder) {
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);
            $this->notifyDuplicateLeadReview($ingestion, $duplicateOrderModel);
            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        }

        // Chia số ngay (auto) hoặc tạo đơn chưa gán (manual/pool). Không nuốt packet.
        if ($hold) {
            if ($session) {
                $session->forceFill(['lead_ingestion_id' => $ingestion->id, 'last_activity_at' => now()])->save();
            }

            $ingestion = $this->allocateFromNormalized($ingestion, $normalized, $campaign, $session, $forceSale);

            if ($ingestion->order_id) {
                $order = Order::query()->find($ingestion->order_id);
                if ($order) {
                    $this->reconcileLandingOrder(
                        $ingestion->fresh(),
                        $order,
                        $campaign,
                        $session,
                    );
                }
            }

            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        }

        if ($forcePending && $forceSale === null) {
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);
            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        }

        $result = $this->allocateFromNormalized($ingestion, $normalized, $campaign, $session, $forceSale);
        $this->pingMarketingDashboard($campaign);

        return $result;
    }

    /**
     * Chốt & chia số cho 1 lead (auto gán sale, hoặc để vào pool chờ chia tay).
     *
     * @param  array<string, mixed>  $normalized
     */
    public function allocateFromNormalized(
        LeadIngestion $ingestion,
        array $normalized,
        ?MarketingSource $campaign = null,
        ?LandingSession $session = null,
        ?User $forceSale = null,
        bool $forcePending = false,
    ): LeadIngestion {
        $phone = $normalized['customer_phone'] ?? $ingestion->customer_phone;
        $requestedSale = $forceSale;
        $companyId = $ingestion->company_id ?? $campaign?->company_id;

        if ($forceSale !== null) {
            $candidateSale = $forceSale;
        } else {
            $assignToSale = ($campaign === null || $campaign->is_approved)
                && $this->allocationResolver->shouldAutoAssign($campaign);
            $candidateSale = $assignToSale ? $this->routing->assignSalesUser($campaign) : null;
        }

        $saleUser = $this->phoneAssignment->resolveSaleForNewOrder(
            is_scalar($phone) ? (string) $phone : null,
            $requestedSale,
            $candidateSale,
            $companyId,
        );

        if ($saleUser && $candidateSale && (int) $candidateSale->id !== (int) $saleUser->id) {
            $payload = is_array($ingestion->payload) ? $ingestion->payload : [];
            $payload['phone_lock_requested_sale_user_id'] = $candidateSale->id;
            $payload['phone_lock_owner_user_id'] = $saleUser->id;
            $ingestion->forceFill([
                'payload' => $payload,
                'phone_lock_conflict' => true,
                'phone_lock_owner_user_id' => $saleUser->id,
            ])->save();
        }

        if (! $saleUser) {
            // Landing/webhook vẫn phải tạo đơn để sale/admin thấy trên tác nghiệp.
            // Không có sale khả dụng → đơn chưa gán (pool), không nuốt packet.
            return DB::transaction(function () use ($ingestion, $normalized, $campaign, $session) {
                $order = $this->orderFactory->createFromLead($ingestion, $normalized, null);

                $ingestion->update([
                    'status' => LeadIngestionStatus::Processed,
                    'order_id' => $order->id,
                    'processed_at' => now(),
                ]);

                if ($session) {
                    $session->forceFill([
                        'order_id' => $order->id,
                        'lead_ingestion_id' => $ingestion->id,
                        'last_activity_at' => now(),
                    ])->save();
                }

                $this->broadcastSafe(
                    new LeadIngested($ingestion, $order),
                    new LeadPoolChanged,
                );
                $this->pingMarketingDashboard($campaign);

                return $ingestion;
            });
        }

        return DB::transaction(function () use ($ingestion, $normalized, $saleUser, $campaign, $session) {
            $order = $this->orderFactory->createFromLead($ingestion, $normalized, $saleUser);
            $lock = $this->phoneAssignment->attachOrder($order, $saleUser, 'lead_allocated');

            if ((bool) $ingestion->phone_lock_conflict) {
                $order->forceFill([
                    'phone_lock_conflict' => true,
                    'phone_lock_note' => 'Đơn được chuyển về Sale đang sở hữu SĐT để tránh hai Sale gọi cùng một khách.',
                ])->save();
            }

            $ingestion->update([
                'status' => LeadIngestionStatus::Processed,
                'order_id' => $order->id,
                'processed_at' => now(),
                'phone_lock_owner_user_id' => $lock->owner_sale_user_id,
            ]);

            if ($session) {
                $session->forceFill([
                    'order_id' => $order->id,
                    'lead_ingestion_id' => $ingestion->id,
                    'last_activity_at' => now(),
                ])->save();
            }

            $this->broadcastSafe(
                new LeadIngested($ingestion, $order),
                new LeadPoolChanged,
                new SaleWorkspaceChanged($saleUser->id),
            );
            $this->notifyNewLead($ingestion, $order, $campaign);

            ActivityLogger::log(
                ActivityLogger::LEAD_INGESTED,
                $ingestion,
                [
                    'order_id' => $order->id,
                    'campaign_id' => $campaign?->id,
                    'customer_phone' => $ingestion->customer_phone,
                ],
                $ingestion->customer_name ?? $ingestion->customer_phone,
            );

            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        });
    }

    /**
     * Kết thúc cửa sổ upsale trên đơn (gọi từ FinalizeLandingLeadJob / đóng phiên JS).
     */
    public function releaseLandingUpsellHold(LeadIngestion $lead): void
    {
        if (! $lead->order_id) {
            return;
        }

        $order = Order::query()->find($lead->order_id);

        if (! $order || ! $order->landing_upsell_hold_until) {
            return;
        }

        $saleUserId = $order->sale_user_id;
        $this->landingUpsell->releaseHold($order);

        if ($saleUserId) {
            $this->broadcastSafe(new SaleWorkspaceChanged($saleUserId));
        }
    }

    /**
     * Chốt 1 lead đang gom (legacy / chia tay) — dựng đơn từ payload đã gộp.
     */
    public function finalizeGatheringLead(LeadIngestion $lead): LeadIngestion
    {
        if ($lead->status !== LeadIngestionStatus::Gathering) {
            return $lead;
        }

        $campaign = $lead->marketing_source_id
            ? MarketingSource::query()->find($lead->marketing_source_id)
            : null;

        $normalized = $this->orderFactory->normalizedFromLead($lead);
        $normalized['utm_campaign'] = $lead->utm_campaign;
        $normalized['utm_source'] = $lead->utm_source;

        $session = LandingSession::query()
            ->where('lead_ingestion_id', $lead->id)
            ->latest('id')
            ->first();

        $result = $this->allocateFromNormalized($lead, $normalized, $campaign, $session);

        if ($campaign && $result->order_id) {
            $order = Order::query()->find($result->order_id);
            if ($order) {
                $this->reconcileLandingOrder($result->fresh(), $order, $campaign, $session);
            }
        }

        return $result;
    }

    /**
     * Mã tham chiếu opaque do JS SaleOps sinh — dùng để trang cảm ơn trỏ về
     * đúng đơn gốc giữa hai domain. Ref chỉ hợp lệ khi đơn còn mở cửa sổ 90 giây.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    protected function extractClientRef(array $rawPayload): ?string
    {
        $key = Arr::get($rawPayload, 'saleops_client_ref')
            ?? Arr::get($rawPayload, 'client_ref');

        if (! is_scalar($key)) {
            return null;
        }

        $key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key) ?? '';

        return $key !== '' ? substr($key, 0, 64) : null;
    }

    /**
     * Dispatch realtime/broadcast events without ever letting a broadcaster
     * outage break lead intake.
     */
    protected function broadcastSafe(object ...$events): void
    {
        foreach ($events as $event) {
            try {
                event($event);
            } catch (\Throwable $e) {
                Log::warning('Realtime broadcast failed (lead intake)', [
                    'event' => $event::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function pingMarketingDashboard(?MarketingSource $campaign): void
    {
        if ($campaign) {
            $this->marketingStats->broadcastForCampaign($campaign);
        }
    }

    protected function notifyNewLead(
        LeadIngestion $ingestion,
        Order $order,
        ?MarketingSource $campaign = null,
    ): void {
        $adminUrl = $campaign && ! $campaign->is_approved
            ? '/admin/marketing/landing-approvals?campaign='.$campaign->id
            : '/admin/leads';

        $data = [
            'variant' => $campaign ? 'landing' : 'platform',
            'campaign_name' => $campaign?->name,
            'platform' => $ingestion->platform,
            'customer_name' => $ingestion->customer_name,
            'customer_phone' => $ingestion->customer_phone,
        ];

        if ($order->sale_user_id) {
            NotificationService::push($order->sale_user_id, 'lead', null, null, '/sales/workspace', $data);
        }

        NotificationService::pushToRole(UserRole::Admin, 'lead', null, null, $adminUrl, $data);
    }

    /** @param  array<string, mixed>  $payload */
    protected function recordFailed(
        string $platform,
        array $payload,
        string $message,
        LeadPacketType $packetType = LeadPacketType::Lead,
        bool $countsAsLead = true,
    ): LeadIngestion {
        return LeadIngestion::query()->create([
            'platform' => $platform,
            'status' => LeadIngestionStatus::Failed,
            'packet_type' => $packetType,
            'counts_as_lead' => $countsAsLead,
            'payload' => $payload,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }
}
