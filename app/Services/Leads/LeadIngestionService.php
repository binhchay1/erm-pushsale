<?php

namespace App\Services\Leads;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Events\LeadIngested;
use App\Events\LeadPoolChanged;
use App\Events\SaleWorkspaceChanged;
use App\Jobs\Leads\FinalizeLandingLeadJob;
use App\Models\LandingSession;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
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
        $normalized['utm_campaign'] = $campaign->utm_campaign;
        $normalized['utm_source'] = $normalized['utm_source'] ?? $campaign->utm_source ?? 'ladipage';

        $phone = $this->sanitizer->normalizePhone($normalized['customer_phone'] ?? null);

        if (! $phone) {
            return $this->recordFailed($driver->platform(), $rawPayload, __('messages.lead_intake.invalid_phone'));
        }

        $normalized['customer_phone'] = $phone;

        $externalId = (string) ($normalized['external_id'] ?? '');

        // Idempotent: cùng packet gửi lại (retry Ladipage) → không nhân đôi.
        if ($externalId !== '') {
            $existing = LeadIngestion::query()
                ->where('platform', $driver->platform())
                ->where(fn ($q) => $q->where('external_id', $externalId)->orWhere('external_id', $externalId.':upsell'))
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $session = $this->resolveSession($campaign, $rawPayload, $phone);

        // 1) Lead đang gom / chờ chia (chưa có đơn) của cùng khách → cộng dồn.
        if ($mergeLead = $this->findActiveGatheringLead($campaign, $phone, $session)) {
            return $this->mergePacketIntoLead($mergeLead, $normalized, $externalId, $session);
        }

        // 2) Đơn vừa tạo trong cửa sổ gom → cộng dồn (upsale muộn / khách bấm chậm).
        if ($order = $this->findRecentOrderForMerge($normalized, $phone, $campaign, $session)) {
            return $this->mergePacketIntoOrder($driver, $order, $campaign, $normalized, $rawPayload, $externalId, $session);
        }

        // 3) Gói tin đầu tiên → tạo lead mới & giữ số (chờ upsale trang cảm ơn).
        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign, $session);
    }

    /**
     * Tìm lead đang gom / chờ chia (CHƯA tạo đơn) của cùng khách để cộng dồn.
     */
    protected function findActiveGatheringLead(MarketingSource $campaign, string $phone, ?LandingSession $session): ?LeadIngestion
    {
        if ($session?->lead_ingestion_id) {
            $viaSession = LeadIngestion::query()
                ->whereKey($session->lead_ingestion_id)
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
        string $phone,
        MarketingSource $campaign,
        ?LandingSession $session = null,
    ): ?Order {
        // Cùng phiên JS (session_id) → luôn gộp vào đơn của phiên, không phụ thuộc cửa sổ 15 phút.
        if ($session?->order_id) {
            $viaSession = Order::query()->find($session->order_id);
            if ($viaSession) {
                return $viaSession;
            }
        }

        // Tham chiếu tường minh từ trang cảm ơn (mã đơn / submission gốc / client_ref JS).
        $parentRef = $normalized['parent_ref'] ?? null;

        if (filled($parentRef)) {
            $byCode = Order::query()->where('order_code', $parentRef)->latest('id')->first();
            if ($byCode) {
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

            if ($viaLead?->order) {
                return $viaLead->order;
            }
        }

        // Theo SĐT trong cửa sổ gom (phút), ưu tiên cùng chiến dịch.
        $windowMinutes = (int) config('saleops.landing.grouping_window_minutes', 15);

        // Đơn đang chờ upsale (trong cửa sổ gom) → gộp upsale vào đó.
        $awaitingUpsell = Order::query()
            ->where('customer_phone', $phone)
            ->where('marketing_source_id', $campaign->id)
            ->where('landing_upsell_locked', false)
            ->whereNotNull('landing_upsell_hold_until')
            ->where('landing_upsell_hold_until', '>', now())
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->latest('id')
            ->first();

        if ($awaitingUpsell) {
            return $awaitingUpsell;
        }

        return Order::query()
            ->where('customer_phone', $phone)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->orderByRaw('CASE WHEN marketing_source_id = ? THEN 0 ELSE 1 END', [$campaign->id])
            ->latest('id')
            ->first();
    }

    /**
     * Cộng dồn 1 gói tin vào lead đang gom (chưa tạo đơn): gộp item + chiết khấu
     * vào payload để khi CHỐT sẽ tạo đúng 1 đơn đủ hàng.
     *
     * @param  array<string, mixed>  $normalized
     */
    protected function mergePacketIntoLead(
        LeadIngestion $lead,
        array $normalized,
        string $externalId,
        ?LandingSession $session,
    ): LeadIngestion {
        $payload = is_array($lead->payload) ? $lead->payload : [];
        $mergedIds = (array) ($payload['merged_ext_ids'] ?? []);

        // Gói tin này đã gộp rồi → bỏ qua (chống nhân đôi item khi gửi lại).
        if ($externalId !== '' && in_array($externalId, $mergedIds, true)) {
            return $lead;
        }

        $incomingItems = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        $existingItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $payload['items'] = array_merge($existingItems, $incomingItems);

        $extraDiscount = (int) ($normalized['discount'] ?? 0);
        if ($extraDiscount > 0) {
            $payload['discount'] = (int) ($payload['discount'] ?? 0) + $extraDiscount;
        }

        // Bổ sung thông tin còn thiếu (địa chỉ khách thường điền ở form đầu).
        if (blank($payload['shipping_address'] ?? null) && filled($normalized['shipping_address'] ?? null)) {
            $payload['shipping_address'] = $normalized['shipping_address'];
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

        // Nhịp hoạt động mới → job giữ số sẽ tự gia hạn thời gian chờ.
        if ($session) {
            $session->forceFill(['last_activity_at' => now()])->save();
        }

        return $lead->refresh();
    }

    /**
     * Cộng dồn gói tin vào ĐƠN đã tạo (upsale muộn): thêm dòng hàng + báo sale.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $rawPayload
     */
    protected function mergePacketIntoOrder(
        LeadPayloadNormalizer $driver,
        Order $order,
        MarketingSource $campaign,
        array $normalized,
        array $rawPayload,
        string $externalId,
        ?LandingSession $session,
    ): LeadIngestion {
        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        $extraDiscount = (int) ($normalized['discount'] ?? 0);

        return DB::transaction(function () use (
            $driver, $order, $campaign, $normalized, $rawPayload, $externalId, $session, $items, $extraDiscount
        ) {
            $order = $order->fresh();

            if (! $order->isLandingUpsellLocked() && ($items !== [] || $extraDiscount > 0)) {
                $this->orderFactory->appendItems($order, $items, $extraDiscount, 'upsell');

                $summary = collect($items)
                    ->map(fn ($i) => (string) ($i['product_name'] ?? $i['name'] ?? ''))
                    ->filter()
                    ->implode(', ');

                if ($summary !== '') {
                    $order->customer_note = trim((string) $order->customer_note."\n[Upsale] ".$summary);
                    $order->save();
                }

                if ($order->landing_upsell_hold_until) {
                    $this->landingUpsell->extendHold($order->fresh());
                }
            }

            $ingestion = LeadIngestion::query()->create([
                'platform' => $driver->platform(),
                'external_id' => ($externalId !== '' ? $externalId : uniqid('up_', true)).':upsell',
                'status' => LeadIngestionStatus::Processed,
                'customer_name' => $normalized['customer_name'] ?? $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'product_interest' => $normalized['product_interest'] ?? null,
                'utm_source' => $normalized['utm_source'],
                'utm_campaign' => $normalized['utm_campaign'],
                'marketing_source_id' => $campaign->id,
                'payload' => $rawPayload,
                'order_id' => $order->id,
                'processed_at' => now(),
            ]);

            if ($session) {
                $session->forceFill(['order_id' => $order->id, 'last_activity_at' => now()])->save();
            }

            if ($order->sale_user_id) {
                $this->broadcastSafe(new SaleWorkspaceChanged($order->sale_user_id));
                NotificationService::push($order->sale_user_id, 'lead', null, null, '/sales/workspace', [
                    'variant' => 'upsell',
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                ]);
            }

            ActivityLogger::log(
                ActivityLogger::LEAD_INGESTED,
                $ingestion,
                [
                    'order_id' => $order->id,
                    'campaign_id' => $campaign->id,
                    'customer_phone' => $order->customer_phone,
                    'upsell' => true,
                    'order_total' => $order->fresh()->total,
                ],
                ($normalized['customer_name'] ?? $order->customer_name).' — upsale',
            );

            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        });
    }

    /**
     * Phiên Landing (JS) — tra theo session_key trong payload; tạo mới nếu chưa có.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    protected function resolveSession(MarketingSource $campaign, array $rawPayload, string $phone): ?LandingSession
    {
        $key = $this->extractSessionKey($rawPayload);

        if ($key === null) {
            return null;
        }

        $session = LandingSession::query()->where('session_key', $key)->first();

        if (! $session) {
            $session = LandingSession::query()->create([
                'company_id' => $campaign->company_id,
                'marketing_source_id' => $campaign->id,
                'session_key' => $key,
                'customer_phone' => $phone,
                'status' => LandingSession::STATUS_OPEN,
                'last_activity_at' => now(),
            ]);

            return $session;
        }

        $session->forceFill([
            'customer_phone' => $session->customer_phone ?: $phone,
            'marketing_source_id' => $session->marketing_source_id ?: $campaign->id,
            'last_activity_at' => now(),
        ])->save();

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

        return $this->ingestNormalized($driver, $rawPayload, $normalized);
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
    ): LeadIngestion {
        $normalized = $driver->normalize($rawPayload);

        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign, null, $forceSale);
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

        $duplicateOrderModel = Order::query()
            ->where('customer_phone', $normalized['customer_phone'])
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->latest('id')
            ->first();
        $duplicateOrder = $duplicateOrderModel !== null;

        // Luồng Landing: chia số ngay, giữ cửa sổ upsale trên đơn (hoặc payload lead nếu chia tay).
        // - Có JS: đóng phiên sớm khi khách rời, tự gia hạn theo hoạt động.
        // - Không JS: chờ theo hold_seconds, gia hạn khi có gói mới, tối đa max_hold_seconds.
        $hold = $campaign !== null && ! $duplicateOrder;

        $willAutoAssign = $hold
            && ($campaign === null || $campaign->is_approved)
            && $this->allocationResolver->shouldAutoAssign($campaign);

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
        $normalizedItems = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        if ($campaign || $normalizedItems !== []) {
            $storedPayload['items'] = $normalizedItems;
        }
        if ((int) ($normalized['discount'] ?? 0) > 0) {
            $storedPayload['discount'] = (int) $normalized['discount'];
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

        $ingestion = LeadIngestion::query()->create([
            'platform' => $driver->platform(),
            'external_id' => $normalized['external_id'],
            'status' => $status,
            'customer_name' => $normalized['customer_name'],
            'customer_phone' => $normalized['customer_phone'],
            'product_interest' => $normalized['product_interest'],
            'utm_source' => $normalized['utm_source'],
            'utm_campaign' => $normalized['utm_campaign'],
            'marketing_source_id' => $campaign?->id,
            'payload' => $storedPayload,
            'error_message' => $exceptionReason,
        ]);

        if ($campaign && ! $duplicateOrder) {
            MarketingSource::query()->whereKey($campaign->id)->increment('contacts');
        }

        if ($duplicateOrder) {
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);
            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        }

        // Chia số ngay (auto) hoặc vào pool (manual); job chỉ kết thúc cửa sổ upsale trên đơn.
        if ($hold) {
            if ($session) {
                $session->forceFill(['lead_ingestion_id' => $ingestion->id, 'last_activity_at' => now()])->save();
            }

            if ($willAutoAssign || $forceSale !== null) {
                $ingestion = $this->allocateFromNormalized($ingestion, $normalized, $campaign, $session, $forceSale);

                if ($ingestion->order_id) {
                    $order = Order::query()->find($ingestion->order_id);
                    if ($order) {
                        $this->landingUpsell->startHold($order);
                    }
                }
            } else {
                $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);
            }

            FinalizeLandingLeadJob::dispatch($ingestion->id, $campaign?->company_id)
                ->delay(now()->addSeconds($this->landingUpsell->holdSeconds()));

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
    ): LeadIngestion {
        // Chia thủ công: gán thẳng cho sale được chọn (không qua auto route / pool).
        if ($forceSale !== null) {
            $saleUser = $forceSale;
        } else {
            $assignToSale = ($campaign === null || $campaign->is_approved)
                && $this->allocationResolver->shouldAutoAssign($campaign);
            $saleUser = $assignToSale ? $this->routing->assignSalesUser() : null;
        }

        if (! $saleUser) {
            if ($ingestion->status !== LeadIngestionStatus::Pending) {
                $ingestion->update(['status' => LeadIngestionStatus::Pending]);
            }
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);
            $this->pingMarketingDashboard($campaign);

            return $ingestion;
        }

        return DB::transaction(function () use ($ingestion, $normalized, $saleUser, $campaign, $session) {
            $order = $this->orderFactory->createFromLead($ingestion, $normalized, $saleUser);
            $ingestion->update([
                'status' => LeadIngestionStatus::Processed,
                'order_id' => $order->id,
                'processed_at' => now(),
            ]);
            $ingestion->refresh();

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

        return $this->allocateFromNormalized($lead, $normalized, $campaign, $session);
    }

    /**
     * Mã tham chiếu gói tin do JS SaleOps sinh (localStorage) — dùng để upsell trang cảm ơn
     * trỏ về đơn gốc kể cả khi quá cửa sổ gom 15 phút.
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
            ? '/admin/landing-approvals?campaign='.$campaign->id
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
    protected function recordFailed(string $platform, array $payload, string $message): LeadIngestion
    {
        return LeadIngestion::query()->create([
            'platform' => $platform,
            'status' => LeadIngestionStatus::Failed,
            'payload' => $payload,
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }
}
