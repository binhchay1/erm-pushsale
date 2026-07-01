<?php

namespace App\Services\Leads;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

class LeadOrderFactory
{
    /**
     * @return array<string, mixed>
     */
    public function normalizedFromLead(LeadIngestion $lead): array
    {
        $payload = is_array($lead->payload) ? $lead->payload : [];

        return [
            'customer_name' => $lead->customer_name,
            'customer_phone' => $lead->customer_phone,
            'product_interest' => $lead->product_interest,
            'utm_source' => $lead->utm_source,
            'utm_campaign' => $lead->utm_campaign,
            'message' => $payload['message'] ?? $payload['note'] ?? null,
            'quantity' => $payload['quantity'] ?? 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function createFromLead(LeadIngestion $lead, array $normalized, ?User $saleUser = null): Order
    {
        $source = $this->resolveCampaign($lead, $normalized);

        // Khách cũ = SĐT đã từng có đơn trước đó (đồng bộ với báo cáo khách mới/cũ).
        $isReturningCustomer = filled($normalized['customer_phone'] ?? null)
            && Order::query()->where('customer_phone', $normalized['customer_phone'])->exists();

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
            'assigned_at' => $saleUser ? now() : null,
            'operation_stage' => OperationStage::NewCustomer->value,
            // Đơn mới chưa chốt → "cần giao/đang tác nghiệp"; chỉ chuyển 'waiting_waybill' khi chốt đơn.
            'delivery_status' => DeliveryStatus::DeliverNow->value,
            'is_duplicate_phone' => false,
            'is_returning_customer' => $isReturningCustomer,
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

        return $this->syncTotals($order->fresh(['items']));
    }

    public function syncTotals(Order $order): Order
    {
        $order->loadMissing('items');
        $subtotal = (int) $order->items->sum(fn ($item) => (int) $item->unit_price * (int) $item->quantity);

        if ($subtotal > 0) {
            $order->update([
                'subtotal' => $subtotal,
                'total' => max(0, $subtotal - (int) $order->discount),
            ]);
        }

        return $order->fresh(['items']);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveCampaign(LeadIngestion $lead, array $normalized): MarketingSource
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
            ['name' => $lead->platform.' — '.($normalized['utm_campaign'] ?? 'default')],
            [
                'utm_source' => $normalized['utm_source'],
                'utm_campaign' => $normalized['utm_campaign'],
                'ad_channel' => $lead->platform,
            ]
        );
    }
}
