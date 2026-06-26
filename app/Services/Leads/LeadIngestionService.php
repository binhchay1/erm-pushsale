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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadIngestionService
{
    public function __construct(
        protected LeadRoutingService $routing,
        protected LeadOrderFactory $orderFactory,
        protected LeadSanitizer $sanitizer,
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
            'payload' => $rawPayload,
        ]);

        if ($duplicateOrder) {
            $this->broadcastSafe(new LeadIngested($ingestion), new LeadPoolChanged);

            return $ingestion;
        }

        $assignToSale = $campaign === null || $campaign->is_approved;
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
