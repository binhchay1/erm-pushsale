<?php

namespace App\Services\Leads;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\LeadIngestionStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Events\LeadIngested;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadIngestionService
{
    public function __construct(
        protected LeadRoutingService $routing,
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
        if (strlen($normalized['customer_phone']) < 9) {
            return $this->recordFailed($driver->platform(), $rawPayload, 'Số điện thoại không hợp lệ');
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
            event(new LeadIngested($ingestion));

            return $ingestion;
        }

        return DB::transaction(function () use ($ingestion, $normalized, $campaign) {
            $order = $this->createOrderFromLead($ingestion, $normalized, $campaign);
            $ingestion->update([
                'status' => LeadIngestionStatus::Processed,
                'order_id' => $order->id,
                'processed_at' => now(),
            ]);
            $ingestion->refresh();
            event(new LeadIngested($ingestion, $order));
            $this->notifyNewLead($ingestion, $order, $campaign);

            return $ingestion;
        });
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function createOrderFromLead(
        LeadIngestion $ingestion,
        array $normalized,
        ?MarketingSource $campaign = null,
    ): Order {
        $source = $campaign ?? $this->resolveCampaign($ingestion, $normalized);
        $assignToSale = $campaign === null || $campaign->is_approved;
        $saleUser = $assignToSale ? $this->routing->assignSalesUser() : null;

        $noteParts = array_filter([
            filled($normalized['message'] ?? null) ? (string) $normalized['message'] : null,
            filled($normalized['product_interest'] ?? null)
                ? 'SP: '.$normalized['product_interest'].(isset($normalized['quantity']) ? ' x'.$normalized['quantity'] : '')
                : null,
        ]);

        $order = Order::query()->create([
            'order_code' => 'PS'.strtoupper(Str::random(10)),
            'sale_user_id' => $saleUser?->id,
            'marketer_user_id' => $source->marketer_user_id,
            'marketing_source_id' => $source->id,
            'product_id' => $source->product_id,
            'customer_name' => $normalized['customer_name'],
            'customer_phone' => $normalized['customer_phone'],
            'customer_note' => $noteParts !== [] ? implode("\n", $noteParts) : null,
            'data_arrived_at' => now(),
            'assigned_at' => $assignToSale ? now() : null,
            'operation_stage' => OperationStage::NewCustomer->value,
            'delivery_status' => 'waiting_waybill',
            'is_duplicate_phone' => false,
            'contact_count' => 1,
        ]);

        if ($source->product_id) {
            $product = Product::query()->find($source->product_id);
            $qty = max(1, (int) ($normalized['quantity'] ?? 1));
            $order->items()->create([
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? ($normalized['product_interest'] ?? 'Sản phẩm'),
                'quantity' => $qty,
                'unit_price' => $product?->unit_price ?? 0,
            ]);
        }

        return $order;
    }

    /**
     * Khớp lead về đúng chiến dịch marketing theo utm_campaign / utm_source.
     * Nếu không khớp chiến dịch nào (campaign chưa tạo) thì tạo nguồn tạm để không mất dữ liệu.
     *
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveCampaign(LeadIngestion $ingestion, array $normalized): MarketingSource
    {
        $campaign = MarketingSource::query()
            ->when($normalized['utm_campaign'] ?? null, fn ($q, $c) => $q->where('utm_campaign', $c))
            ->when(
                empty($normalized['utm_campaign']) && ! empty($normalized['utm_source']),
                fn ($q) => $q->where('utm_source', $normalized['utm_source']),
            )
            ->when(! empty($normalized['utm_campaign']) || ! empty($normalized['utm_source']),
                fn ($q) => $q->where('is_active', true)->orderByDesc('id'),
            )
            ->first();

        if ($campaign) {
            return $campaign;
        }

        return MarketingSource::query()->firstOrCreate(
            ['name' => $ingestion->platform.' — '.($normalized['utm_campaign'] ?? 'default')],
            [
                'utm_source' => $normalized['utm_source'],
                'utm_campaign' => $normalized['utm_campaign'],
                'ad_channel' => $ingestion->platform,
            ]
        );
    }

    protected function notifyNewLead(
        LeadIngestion $ingestion,
        Order $order,
        ?MarketingSource $campaign = null,
    ): void {
        $title = $campaign
            ? 'Lead Landing — '.$campaign->name
            : 'Lead mới từ '.$ingestion->platform;
        $message = trim(($ingestion->customer_name ?? 'Khách').' · '.($ingestion->customer_phone ?? ''));
        $adminUrl = '/admin/leads';

        if ($order->sale_user_id) {
            NotificationService::push($order->sale_user_id, 'lead', $title, $message, '/sales/workspace');
        }

        NotificationService::pushToRole(UserRole::Admin, 'lead', $title, $message, $adminUrl);

        if ($campaign && ! $campaign->is_approved) {
            NotificationService::pushToRole(
                UserRole::Admin,
                'lead',
                'Cần duyệt Landing: '.$campaign->name,
                'Lead test chờ duyệt — chưa chia số Sale',
                '/admin/landing-approvals',
            );
        }
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
