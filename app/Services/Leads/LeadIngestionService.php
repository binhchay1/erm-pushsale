<?php

namespace App\Services\Leads;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Events\LeadIngested;
use App\Events\LeadPoolChanged;
use App\Events\SaleWorkspaceChanged;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Services\NotificationService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadIngestionService
{
    public function __construct(
        protected LeadRoutingService $routing,
        protected LeadOrderFactory $orderFactory,
        protected LeadSanitizer $sanitizer,
        protected LeadAllocationResolver $allocationResolver,
    ) {}

    /**
     * Lead từ URL API riêng của chiến dịch Landing (Ladipage).
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingestForCampaign(
        LeadPayloadNormalizer $driver,
        MarketingSource $campaign,
        array $rawPayload,
    ): LeadIngestion {
        $normalized = $driver->normalize($rawPayload);
        $normalized['utm_campaign'] = $campaign->utm_campaign;
        $normalized['utm_source'] = $normalized['utm_source'] ?? $campaign->utm_source ?? 'ladipage';

        return $this->ingestNormalized($driver, $rawPayload, $normalized, $campaign);
    }

    /**
     * Upsale từ trang cảm ơn (Ladipage): cộng thêm sản phẩm vào ĐƠN CŨ của khách,
     * KHÔNG tạo lead/đơn mới để tránh trùng số & lệch chia số.
     *
     * Nếu không tìm được đơn gốc (khách chưa từng đặt) → xử lý như lead thường
     * để không mất dữ liệu mua thêm.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function ingestUpsellForCampaign(
        LeadPayloadNormalizer $driver,
        MarketingSource $campaign,
        array $rawPayload,
    ): LeadIngestion {
        $normalized = $driver->normalize($rawPayload);
        $normalized['utm_campaign'] = $campaign->utm_campaign;
        $normalized['utm_source'] = $normalized['utm_source'] ?? $campaign->utm_source ?? 'ladipage';

        $phone = $this->sanitizer->normalizePhone($normalized['customer_phone'] ?? null);

        if (! $phone) {
            return $this->recordFailed($driver->platform(), $rawPayload, __('messages.lead_intake.invalid_phone'));
        }

        $normalized['customer_phone'] = $phone;

        $order = $this->findUpsellTargetOrder($normalized, $phone, $campaign);

        // Không có đơn gốc → coi như đơn mới bình thường (chia số như thường lệ).
        if (! $order) {
            return $this->ingestForCampaign($driver, $campaign, $rawPayload);
        }

        $upsellExternalId = ((string) ($normalized['external_id'] ?? uniqid('up_', true))).':upsell';

        $existing = LeadIngestion::query()
            ->where('platform', $driver->platform())
            ->where('external_id', $upsellExternalId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        $extraDiscount = (int) ($normalized['discount'] ?? 0);

        return DB::transaction(function () use (
            $driver, $rawPayload, $normalized, $campaign, $order, $items, $extraDiscount, $upsellExternalId, $phone
        ) {
            if ($items !== [] || $extraDiscount > 0) {
                $this->orderFactory->appendItems($order, $items, $extraDiscount, 'upsell');

                $summary = collect($items)
                    ->map(fn ($i) => (string) ($i['product_name'] ?? $i['name'] ?? ''))
                    ->filter()
                    ->implode(', ');

                if ($summary !== '') {
                    $order->customer_note = trim((string) $order->customer_note."\n[Upsale] ".$summary);
                    $order->save();
                }
            }

            $ingestion = LeadIngestion::query()->create([
                'platform' => $driver->platform(),
                'external_id' => $upsellExternalId,
                'status' => LeadIngestionStatus::Processed,
                'customer_name' => $normalized['customer_name'] ?? $order->customer_name,
                'customer_phone' => $phone,
                'product_interest' => $normalized['product_interest'] ?? null,
                'utm_source' => $normalized['utm_source'],
                'utm_campaign' => $normalized['utm_campaign'],
                'marketing_source_id' => $campaign->id,
                'payload' => $rawPayload,
                'order_id' => $order->id,
                'processed_at' => now(),
            ]);

            if ($order->sale_user_id) {
                $this->broadcastSafe(new SaleWorkspaceChanged($order->sale_user_id));
            }

            ActivityLogger::log(
                ActivityLogger::LEAD_INGESTED,
                $ingestion,
                [
                    'order_id' => $order->id,
                    'campaign_id' => $campaign->id,
                    'customer_phone' => $phone,
                    'upsell' => true,
                    'order_total' => $order->fresh()->total,
                ],
                ($normalized['customer_name'] ?? $order->customer_name).' — upsale',
            );

            return $ingestion;
        });
    }

    /**
     * Tìm đơn gốc để cộng thêm sản phẩm upsale.
     *
     * @param  array<string, mixed>  $normalized
     */
    protected function findUpsellTargetOrder(array $normalized, string $phone, MarketingSource $campaign): ?Order
    {
        // 1) Tham chiếu tường minh từ trang cảm ơn (mã đơn / submission gốc).
        $parentRef = $normalized['parent_ref'] ?? null;

        if (filled($parentRef)) {
            $byCode = Order::query()->where('order_code', $parentRef)->latest('id')->first();
            if ($byCode) {
                return $byCode;
            }

            $viaLead = LeadIngestion::query()
                ->where('external_id', $parentRef)
                ->whereNotNull('order_id')
                ->latest('id')
                ->first();

            if ($viaLead?->order) {
                return $viaLead->order;
            }
        }

        // 2) Theo SĐT trong cửa sổ chống trùng (mặc định 30 ngày), ưu tiên cùng chiến dịch.
        $windowDays = (int) config('saleops.lead_routing.duplicate_window_days', 30);

        return Order::query()
            ->where('customer_phone', $phone)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->orderByRaw('CASE WHEN marketing_source_id = ? THEN 0 ELSE 1 END', [$campaign->id])
            ->latest('id')
            ->first();
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
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, mixed>  $normalized
     */
    protected function ingestNormalized(
        LeadPayloadNormalizer $driver,
        array $rawPayload,
        array $normalized,
        ?MarketingSource $campaign = null,
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

        $duplicateOrder = Order::query()
            ->where('customer_phone', $normalized['customer_phone'])
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->exists();

        $ingestion = LeadIngestion::query()->create([
            'platform' => $driver->platform(),
            'external_id' => $normalized['external_id'],
            'status' => $duplicateOrder ? LeadIngestionStatus::Duplicate : LeadIngestionStatus::Pending,
            'customer_name' => $normalized['customer_name'],
            'customer_phone' => $normalized['customer_phone'],
            'product_interest' => $normalized['product_interest'],
            'utm_source' => $normalized['utm_source'],
            'utm_campaign' => $normalized['utm_campaign'],
            'marketing_source_id' => $campaign?->id,
            'payload' => $rawPayload,
        ]);

        if ($duplicateOrder) {
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);

            return $ingestion;
        }

        // Chia tự động khi: chiến dịch đã duyệt + cấu hình chiến dịch/hệ thống cho phép auto.
        $assignToSale = ($campaign === null || $campaign->is_approved)
            && $this->allocationResolver->shouldAutoAssign($campaign);
        $saleUser = $assignToSale ? $this->routing->assignSalesUser() : null;

        if (! $saleUser) {
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);

            return $ingestion;
        }

        return DB::transaction(function () use ($ingestion, $normalized, $saleUser, $campaign) {
            $order = $this->orderFactory->createFromLead($ingestion, $normalized, $saleUser);
            $ingestion->update([
                'status' => LeadIngestionStatus::Processed,
                'order_id' => $order->id,
                'processed_at' => now(),
            ]);
            $ingestion->refresh();
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

            return $ingestion;
        });
    }

    /**
     * Dispatch realtime/broadcast events without ever letting a broadcaster
     * outage break lead intake. Events are queued (ShouldBroadcast) but we still
     * guard the dispatch for the sync-queue edge case.
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
