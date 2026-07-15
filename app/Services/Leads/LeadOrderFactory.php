<?php

namespace App\Services\Leads;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Integrations\Landing\LandingFormDriver;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadOrderFactory
{
    /**
     * @return array<string, mixed>
     */
    public function normalizedFromLead(LeadIngestion $lead): array
    {
        $payload = is_array($lead->payload) ? $lead->payload : [];

        // Tách lại combo/chiết khấu/địa chỉ từ payload gốc (dùng cho luồng chia số thủ công).
        $extra = (new LandingFormDriver)->normalize($payload);

        return [
            'customer_name' => $lead->customer_name,
            'customer_phone' => $lead->customer_phone,
            'product_interest' => $lead->product_interest,
            'utm_source' => $lead->utm_source,
            'utm_campaign' => $lead->utm_campaign,
            'message' => $payload['message'] ?? $payload['note'] ?? null,
            'quantity' => $payload['quantity'] ?? 1,
            'shipping_address' => $extra['shipping_address'] ?? null,
            'shipping_notes' => $payload['shipping_notes'] ?? null,
            'discount' => (int) ($extra['discount'] ?? 0),
            'deposit' => (int) ($payload['deposit'] ?? 0),
            'shipping_fee_collected' => (int) ($payload['shipping_fee_collected'] ?? $extra['shipping_fee_collected'] ?? 0),
            'items' => $extra['items'] ?? [],
            'item_origin' => $payload['item_origin'] ?? 'landing',
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

        // Cột customer_note chỉ chứa nội dung khách nhập. Tên/SL/giá sản
        // phẩm đã có cấu trúc riêng trong order_items, không được trộn vào tin nhắn.
        $noteParts = array_filter([
            filled($normalized['message'] ?? null) ? (string) $normalized['message'] : null,
        ]);

        $comboItems = $this->buildItemRows($normalized['items'] ?? [], $normalized['item_origin'] ?? 'landing');

        $order = Order::query()->create([
            // Mã đơn chỉ được cấp khi sale chốt đơn thành công.
            'order_code' => null,
            'sale_user_id' => $saleUser?->id,
            'marketer_user_id' => $source->marketer_user_id,
            'marketing_source_id' => $source->id,
            'product_id' => $source->product_id,
            'customer_name' => $normalized['customer_name'],
            'customer_phone' => $normalized['customer_phone'],
            'customer_note' => $noteParts !== [] ? implode("\n", $noteParts) : null,
            'shipping_address' => $normalized['shipping_address'] ?? null,
            'shipping_notes' => $normalized['shipping_notes'] ?? null,
            'discount' => (int) ($normalized['discount'] ?? 0),
            'deposit' => (int) ($normalized['deposit'] ?? 0),
            'shipping_fee_collected' => (int) ($normalized['shipping_fee_collected'] ?? 0),
            'data_arrived_at' => now(),
            'assigned_at' => $saleUser ? now() : null,
            'operation_stage' => OperationStage::NewCustomer->value,
            // Đơn mới chưa chốt → "cần giao/đang tác nghiệp"; chỉ chuyển 'waiting_waybill' khi chốt đơn.
            'delivery_status' => DeliveryStatus::DeliverNow->value,
            'is_duplicate_phone' => false,
            'is_returning_customer' => $isReturningCustomer,
            'contact_count' => 1,
        ]);

        // Có combo/gói khách chọn trên landing → dùng đúng những dòng đó (tránh nhân đôi với SP mặc định).
        if ($comboItems !== []) {
            foreach ($comboItems as $row) {
                $order->items()->create($row);
            }
        } elseif ($source->product_id) {
            $product = Product::query()->find($source->product_id);
            $qty = max(1, (int) ($normalized['quantity'] ?? 1));
            $order->items()->create([
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? ($normalized['product_interest'] ?? 'Sản phẩm'),
                'item_type' => 'product',
                'origin' => $normalized['item_origin'] ?? 'landing',
                'quantity' => $qty,
                'unit_price' => $product?->unit_price ?? 0,
                'cost_price' => $this->productCost($product),
            ]);
        }

        return $this->syncTotals($order->fresh(['items']));
    }

    /**
     * Chuẩn hóa danh sách item normalized → thuộc tính OrderItem.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function buildItemRows(array $items, string $defaultOrigin = 'system'): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'type', 'cost_price'])
            ->keyBy('id');
        $comboCosts = $this->comboCosts($productIds);
        $rows = [];

        foreach ($items as $item) {
            $name = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $productId = filled($item['product_id'] ?? null) ? (int) $item['product_id'] : null;
            $product = $productId ? $products->get($productId) : null;
            $costPrice = $product
                ? max(0, (int) ($product->cost_price ?: ($comboCosts[$productId] ?? 0)))
                : 0;

            $rows[] = [
                'product_id' => $productId,
                'product_name' => $name,
                'item_type' => in_array($item['item_type'] ?? null, ['product', 'combo', 'upsell', 'gift'], true)
                    ? $item['item_type']
                    : 'combo',
                'origin' => $item['origin'] ?? $defaultOrigin,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                // Giá bán có thể đến từ mapping backend của kết nối landing; giá vốn
                // tuyệt đối không lấy từ request/client mà luôn tra từ catalog nội bộ.
                'unit_price' => max(0, (int) ($item['unit_price'] ?? 0)),
                'cost_price' => $costPrice,
                'discount_amount' => max(0, (int) ($item['discount_amount'] ?? 0)),
                'meta' => $item['meta'] ?? null,
            ];
        }

        return $rows;
    }

    private function productCost(?Product $product): int
    {
        if (! $product) {
            return 0;
        }

        $cost = max(0, (int) $product->cost_price);
        if ($cost > 0 || ! $product->isCombo()) {
            return $cost;
        }

        return (int) ($this->comboCosts(collect([$product->id]))[$product->id] ?? 0);
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function comboCosts(\Illuminate\Support\Collection $productIds): \Illuminate\Support\Collection
    {
        if ($productIds->isEmpty() || ! \Illuminate\Support\Facades\Schema::hasTable('product_combo_items')) {
            return collect();
        }

        return DB::table('product_combo_items as combo_items')
            ->join('products as components', 'components.id', '=', 'combo_items.component_product_id')
            ->whereIn('combo_items.combo_product_id', $productIds)
            ->selectRaw('combo_items.combo_product_id, SUM(combo_items.quantity * COALESCE(components.cost_price, 0)) as aggregate_cost')
            ->groupBy('combo_items.combo_product_id')
            ->pluck('aggregate_cost', 'combo_items.combo_product_id')
            ->map(fn ($value): int => max(0, (int) $value));
    }

    /**
     * Cộng thêm dòng hàng (thường là upsale trang cảm ơn) vào đơn có sẵn rồi đồng bộ tổng tiền.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function appendItems(Order $order, array $items, int $extraDiscount = 0, string $origin = 'upsell'): Order
    {
        $rows = $this->buildItemRows($items, $origin);

        foreach ($rows as $row) {
            $order->items()->create($row);
        }

        if ($extraDiscount > 0) {
            $order->discount = (int) $order->discount + $extraDiscount;
            $order->save();
        }

        return $this->syncTotals($order->fresh(['items']));
    }

    public function syncTotals(Order $order): Order
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return $order;
        }

        $subtotal = (int) $order->items->sum(fn ($item) => (int) $item->unit_price * (int) $item->quantity);
        $itemsDiscount = (int) $order->items->sum(fn ($item) => (int) $item->discount_amount);

        // Giá trị cuối đơn = tổng dòng − chiết khấu theo dòng − chiết khấu cấp đơn.
        $total = max(0, $subtotal - $itemsDiscount - (int) $order->discount);
        $amountToCollect = max(0, $total + (int) $order->shipping_fee_collected - (int) $order->deposit);

        $order->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'amount_to_collect' => $amountToCollect,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveCampaign(LeadIngestion $lead, array $normalized): MarketingSource
    {
        if ($lead->marketing_source_id) {
            $linked = MarketingSource::query()->find($lead->marketing_source_id);
            if ($linked) {
                return $linked;
            }
        }

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
