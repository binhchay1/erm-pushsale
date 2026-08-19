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
use App\Services\Orders\DiscountCodRuleResolver;
use App\Support\LandingProductLabel;
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
            'marketer_user_id' => filled($payload['marketer_user_id'] ?? null) ? (int) $payload['marketer_user_id'] : null,
            'facebook_page_id' => $payload['facebook_page_id'] ?? null,
            'facebook_page_name' => $payload['facebook_page_name'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function createFromLead(LeadIngestion $lead, array $normalized, ?User $saleUser = null): Order
    {
        $source = $this->resolveCampaign($lead, $normalized);
        $landingConnection = $lead->landing_connection_id
            ? $lead->landingConnection
            : $source->landingConnection()->first();

        if ($landingConnection) {
            $landingConnection->loadMissing([
                'products.product.children' => fn ($query) => $query->where('is_active', true),
                'products.product' => fn ($query) => $query->where('is_active', true),
            ]);
        }

        // Khách cũ / trùng số = SĐT đã từng có đơn trước đó (hiển thị icon như Pushsale).
        $isReturningCustomer = filled($normalized['customer_phone'] ?? null)
            && Order::query()->where('customer_phone', $normalized['customer_phone'])->exists();

        $payloadItems = $this->buildItemRows($normalized['items'] ?? [], $normalized['item_origin'] ?? 'landing');

        // Kết nối landing có SP/SKU catalog → luôn dùng các dòng đó (SL = 0).
        // Combo / form_item text của Ladi không bao giờ thành dòng hàng — kể cả khi
        // webhook gắn product_id lạ — để sale mở đơn thấy đúng SKU như Push.
        $connectionItems = $this->buildItemRows(
            $this->landingConnectionDefaultItems($landingConnection),
            $normalized['item_origin'] ?? 'landing',
        );
        $mappedPayloadItems = array_values(array_filter(
            $payloadItems,
            fn (array $row) => filled($row['product_id'] ?? null),
        ));
        $comboItems = $connectionItems !== [] ? $connectionItems : $mappedPayloadItems;

        // Tin nhắn theo form khách yêu cầu: địa chỉ khách để lại (dựng ở presenter)
        // + combo khách mua + sản phẩm mua thêm.
        $noteParts = array_values(array_filter([
            filled($normalized['message'] ?? null) ? (string) $normalized['message'] : null,
            ...$this->landingLabelNotes($payloadItems),
        ]));

        $order = Order::query()->create([
            // Mã đơn chỉ được cấp khi sale chốt đơn thành công.
            'order_code' => null,
            'sale_user_id' => $saleUser?->id,
            'marketer_user_id' => $source->marketer_user_id,
            'marketing_source_id' => $source->id,
            'landing_connection_id' => $lead->landing_connection_id ?: $landingConnection?->id,
            'landing_connection_source_id' => $lead->landing_connection_source_id,
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
            'is_duplicate_phone' => $isReturningCustomer,
            'is_returning_customer' => $isReturningCustomer,
            'contact_count' => 1,
        ]);

        // Dòng hàng: SP/SKU catalog của kết nối landing (SL = 0). Combo Ladi nằm ở tin nhắn.
        if ($comboItems !== []) {
            foreach ($comboItems as $row) {
                $order->items()->create($row);
            }
        }

        // Đơn cùng SĐT (kể cả đơn cũ) đều gắn cờ trùng số để icon hiện trên sale/kho/KT.
        if ($isReturningCustomer && filled($normalized['customer_phone'] ?? null)) {
            Order::query()
                ->where('customer_phone', $normalized['customer_phone'])
                ->where('is_duplicate_phone', false)
                ->update(['is_duplicate_phone' => true]);
            $order->is_duplicate_phone = true;
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
            $rawName = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
            $name = LandingProductLabel::sanitizeName($rawName);
            if ($name === null) {
                continue;
            }

            $productId = filled($item['product_id'] ?? null) ? (int) $item['product_id'] : null;
            $product = $productId ? $products->get($productId) : null;
            $costPrice = $product
                ? max(0, (int) ($product->cost_price ?: ($comboCosts[$productId] ?? 0)))
                : 0;

            $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
            if ($rawName !== $name) {
                $meta['raw_label'] = $rawName;
            }

            $rows[] = [
                'product_id' => $productId,
                'product_name' => $name,
                'item_type' => in_array($item['item_type'] ?? null, ['product', 'combo', 'upsell', 'gift'], true)
                    ? $item['item_type']
                    : 'combo',
                'origin' => $item['origin'] ?? $defaultOrigin,
                'quantity' => array_key_exists('quantity', $item) ? max(0, (int) $item['quantity']) : 1,
                // Giá bán có thể đến từ mapping backend của kết nối landing; giá vốn
                // tuyệt đối không lấy từ request/client mà luôn tra từ catalog nội bộ.
                'unit_price' => max(0, (int) ($item['unit_price'] ?? 0)),
                'cost_price' => $costPrice,
                'discount_amount' => max(0, (int) ($item['discount_amount'] ?? 0)),
                'meta' => $meta !== [] ? $meta : ($item['meta'] ?? null),
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
     * Nhãn gói/combo landing (không map catalog) → dòng ghi chú cho cột tin nhắn.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public function landingLabelNotes(array $rows): array
    {
        $labels = ['combo' => [], 'upsell' => []];

        foreach ($rows as $row) {
            $type = (string) ($row['item_type'] ?? '');
            $hasCatalogId = filled($row['product_id'] ?? null);
            $isPackageLabel = in_array($type, ['combo', 'upsell'], true) || ! $hasCatalogId;
            if (! $isPackageLabel) {
                continue;
            }

            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $label = trim((string) ($meta['raw_label'] ?? $row['product_name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $bucket = $type === 'upsell' ? 'upsell' : 'combo';
            if (! in_array($label, $labels[$bucket], true)) {
                $labels[$bucket][] = $label;
            }
        }

        return array_values(array_filter([
            $labels['combo'] !== []
                ? __('messages.landing.combo_note', ['value' => implode(' + ', $labels['combo'])])
                : null,
            $labels['upsell'] !== []
                ? __('messages.landing.upsell_note', ['value' => implode(' + ', $labels['upsell'])])
                : null,
        ]));
    }

    /**
     * Cộng thêm dòng hàng (thường là upsale trang cảm ơn) vào đơn có sẵn rồi đồng bộ tổng tiền.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  bool  $labelsToNote  Nhãn text không map catalog → tin nhắn thay vì dòng hàng.
     */
    public function appendItems(Order $order, array $items, int $extraDiscount = 0, string $origin = 'upsell', bool $labelsToNote = false): Order
    {
        $rows = $this->buildItemRows($items, $origin);

        if ($labelsToNote) {
            $notes = $this->landingLabelNotes($rows);
            $rows = array_values(array_filter($rows, fn (array $row) => filled($row['product_id'] ?? null)));

            if ($notes !== []) {
                $this->appendCustomerNote($order, $notes);
            }
        }

        foreach ($rows as $row) {
            $order->items()->create($row);
        }

        if ($extraDiscount > 0) {
            $order->discount = (int) $order->discount + $extraDiscount;
            $order->save();
        }

        return $this->syncTotals($order->fresh(['items']));
    }

    /**
     * @param  list<string>  $notes
     */
    private function appendCustomerNote(Order $order, array $notes): void
    {
        $current = trim((string) ($order->customer_note ?? ''));
        $missing = array_values(array_filter(
            $notes,
            fn (string $note) => $note !== '' && ! str_contains($current, $note),
        ));

        if ($missing === []) {
            return;
        }

        $order->customer_note = trim(implode("\n", array_filter([$current, ...$missing])));
        $order->save();
    }

    public function syncTotals(Order $order): Order
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return $order;
        }

        $subtotal = (int) $order->items->sum(fn ($item) => (int) $item->unit_price * (int) $item->quantity);
        $itemsDiscount = (int) $order->items->sum(fn ($item) => (int) $item->discount_amount);

        // Combo được tính như một dòng catalog có giá riêng. Thiết lập 1.9 chỉ
        // tự gợi/áp dụng khi đơn chưa có chiết khấu hoặc COD thu khách từ nguồn nhập.
        $resolver = new DiscountCodRuleResolver;
        $discount = (int) $order->discount;
        if ($discount <= 0) {
            $discount = $resolver->discountForSubtotal($subtotal);
        }
        $shippingFeeCollected = (int) $order->shipping_fee_collected;
        if ($shippingFeeCollected <= 0) {
            $shippingFeeCollected = $resolver->codFeeForSubtotal(max(0, $subtotal - $itemsDiscount - $discount));
        }

        // Giá trị cuối đơn = tổng dòng − chiết khấu theo dòng − chiết khấu cấp đơn.
        $total = max(0, $subtotal - $itemsDiscount - $discount);
        $amountToCollect = max(0, $total + $shippingFeeCollected - (int) $order->deposit);

        $order->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee_collected' => $shippingFeeCollected,
            'total' => $total,
            'amount_to_collect' => $amountToCollect,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * SP/SKU mặc định gắn trên kết nối landing khi payload không có dòng hàng.
     * Parent có phân loại → thêm từng SKU con (SL = 0, sale chỉnh số lượng).
     *
     * @return list<array<string, mixed>>
     */
    private function landingConnectionDefaultItems(?\App\Models\LandingConnection $landingConnection): array
    {
        if (! $landingConnection) {
            return [];
        }

        $mappings = $landingConnection->products
            ->filter(fn ($mapping) => $mapping->product && $mapping->product->is_active)
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($mappings->isEmpty()) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach ($mappings as $mapping) {
            $product = $mapping->product;
            $children = $product->relationLoaded('children')
                ? $product->children
                : $product->children()->where('is_active', true)->get();

            $targets = $children->isNotEmpty() ? $children : collect([$product]);

            foreach ($targets as $target) {
                $id = (int) $target->id;
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                $items[] = [
                    'product_id' => $id,
                    'product_name' => $target->name,
                    'name' => $target->name,
                    'item_type' => $mapping->item_type ?: 'product',
                    'quantity' => 0,
                    'unit_price' => (int) ($mapping->unit_price_override ?? $target->unit_price ?? 0),
                    'origin' => 'landing_connection',
                    'meta' => [
                        'landing_connection_product_id' => $mapping->id,
                        'parent_product_id' => $product->id !== $id ? $product->id : null,
                    ],
                ];
            }
        }

        return $items;
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
            ['name' => $lead->platform.' — '.($normalized['facebook_page_name'] ?? $normalized['utm_campaign'] ?? 'default')],
            [
                'marketer_user_id' => $normalized['marketer_user_id'] ?? null,
                'utm_source' => $normalized['utm_source'],
                'utm_campaign' => $normalized['facebook_page_id'] ?? $normalized['utm_campaign'],
                'ad_channel' => $lead->platform === 'facebook' ? 'Facebook' : $lead->platform,
                'is_active' => true,
                'is_approved' => true,
            ]
        );
    }
}
