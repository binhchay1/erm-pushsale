<?php

namespace App\Services\Warehouse;

use App\Models\Order;
use App\Models\User;
use App\Services\Settings\FeatureSettingsService;
use App\Services\Shipping\CreateShipmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingLabelPrintService
{
    public function __construct(
        private readonly FeatureSettingsService $featureSettings,
        private readonly CreateShipmentService $shipments,
        private readonly WarehouseOrderActionService $warehouseActions,
    ) {}

    /** @return list<array<string, mixed>> */
    public function fabButtons(): array
    {
        $order = config('shipping_print_profiles.fab_order', []);
        $profiles = config('shipping_print_profiles.profiles', []);

        return collect($order)
            ->map(fn (string $key) => $profiles[$key] ?? null)
            ->filter()
            ->map(fn (array $profile) => [
                'key' => $profile['key'],
                'title' => $profile['title'],
                'tone' => $profile['tone'] ?? 'success',
                'icon' => 'print',
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function profile(string $key): array
    {
        $profile = config("shipping_print_profiles.profiles.{$key}");
        if (! is_array($profile)) {
            throw ValidationException::withMessages(['profile' => 'Mẫu in không hợp lệ.']);
        }

        return $profile;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    public function buildPage(string $profileKey, array $ids, ?User $actor = null): array
    {
        $profile = $this->profile($profileKey);
        $limit = (int) ($profile['max_quantity'] ?? 2000);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => 'Chọn ít nhất 1 đơn để in.']);
        }
        if (count($ids) > $limit) {
            throw ValidationException::withMessages(['ids' => "Mỗi lần in tối đa {$limit} đơn."]);
        }

        $orders = Order::query()
            ->with([
                'items.product',
                'warehouse',
                'saleUser',
                'shipments' => fn ($q) => $q->latest('id'),
            ])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Order $order) => array_search($order->id, $ids, true))
            ->values();

        $matched = $this->filterByProfile($orders, $profile);
        $unmatched = $orders->filter(fn (Order $order) => ! $matched->contains('id', $order->id))->values();

        return [
            'profile' => $this->presentProfile($profile),
            'printButtons' => $this->fabButtons(),
            'defaults' => $this->defaultSettings($profile),
            'featureFlags' => [
                'watermark_logo' => $this->featureSettings->bool('SettingInDonAnhChim', false),
                'fixed_receiver_phone' => $this->featureSettings->string('SettingDangDonNguoiNhanSDT', ''),
                'default_sender' => $this->featureSettings->string('SettingDangDonNguoiGui', ''),
                'app_name' => (string) config('app.name'),
            ],
            'labels' => $matched->map(fn (Order $order) => $this->presentLabel($order, $profile))->values()->all(),
            'unmatched' => $unmatched->map(fn (Order $order) => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'shipping_provider' => $order->shipping_provider,
                'shipping_method' => $order->shipping_method,
                'message' => 'Đơn không khớp mẫu in / đơn vị giao hàng của nút này.',
            ])->values()->all(),
            'grouped' => $this->groupLabels($matched, $profile),
            'counts' => [
                'selected' => $orders->count(),
                'printable' => $matched->count(),
                'unmatched' => $unmatched->count(),
            ],
            'actor' => $actor?->only(['id', 'name']),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{message:string,count:int}
     */
    public function markPrinted(array $ids, ?User $actor): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $orders = Order::query()->whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            $this->warehouseActions->markPrinted($order, $actor);
        }

        return [
            'message' => 'Đã đánh dấu in '.$orders->count().' đơn.',
            'count' => $orders->count(),
        ];
    }

    /**
     * Attempt carrier label for one order; returns JSON-friendly payload.
     *
     * @return array<string, mixed>
     */
    public function carrierLabelPayload(Order $order, ?string $provider = null): array
    {
        try {
            $result = $this->shipments->printLabel($order, $provider);
            if (! ($result['success'] ?? false)) {
                return [
                    'ok' => false,
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'message' => $result['message'] ?? 'Không lấy được nhãn.',
                    'data' => $result['data'] ?? null,
                ];
            }

            $binary = $result['binary'] ?? null;
            $contentType = $result['content_type'] ?? 'application/pdf';

            return [
                'ok' => true,
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'content_type' => $contentType,
                'base64' => is_string($binary) ? base64_encode($binary) : null,
                'url' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'message' => $e->getMessage() ?: 'Config is not exist.',
            ];
        }
    }

    /** @param  Collection<int, Order>  $orders */
    private function filterByProfile(Collection $orders, array $profile): Collection
    {
        $providers = $profile['providers'] ?? null;
        $tokens = array_map('mb_strtolower', $profile['match_tokens'] ?? []);
        if ($providers === null && $tokens === []) {
            return $orders;
        }

        return $orders->filter(function (Order $order) use ($providers, $tokens) {
            $provider = mb_strtolower((string) ($order->shipping_provider ?: ''));
            $method = mb_strtolower((string) ($order->shipping_method ?: ''));
            $haystack = $provider.' '.$method;

            if (is_array($providers) && $providers !== []) {
                foreach ($providers as $allowed) {
                    if ($provider === mb_strtolower((string) $allowed)) {
                        return true;
                    }
                }
            }

            foreach ($tokens as $token) {
                if ($token !== '' && Str::contains($haystack, $token)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array{title:string,warehouse_id:?int,labels:list<array<string,mixed>>}>
     */
    private function groupLabels(Collection $orders, array $profile): array
    {
        if (! ($profile['group_by_warehouse'] ?? false)) {
            return [[
                'title' => 'Đơn in',
                'warehouse_id' => null,
                'labels' => $orders->map(fn (Order $order) => $this->presentLabel($order, $profile))->values()->all(),
            ]];
        }

        return $orders->groupBy(fn (Order $order) => $order->warehouse_id ?: 0)
            ->map(function (Collection $group) use ($profile) {
                $warehouse = $group->first()?->warehouse;

                return [
                    'title' => 'Đơn in kho : '.($warehouse?->name ?: 'Chưa gắn kho'),
                    'warehouse_id' => $warehouse?->id,
                    'labels' => $group->map(fn (Order $order) => $this->presentLabel($order, $profile))->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function presentLabel(Order $order, array $profile): array
    {
        $shipment = $order->shipments->first();
        $geo = is_array($order->shipping_geo) ? $order->shipping_geo : [];
        $receiverPhone = (string) ($order->receiver_phone ?: $order->customer_phone);
        $receiverName = (string) ($order->receiver_name ?: $order->customer_name);
        $address = trim((string) ($order->shipping_address_2 ?: $order->shipping_address ?: ($geo['address'] ?? '')));
        $province = (string) ($geo['province'] ?? '');
        $cod = (int) ($order->amount_to_collect ?: max(0, (int) $order->total - (int) $order->deposit));
        $senderNote = $order->warehouse?->sender_print_note
            ?: $this->featureSettings->string('SettingDangDonNguoiGui', '');

        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'tracking_number' => $shipment?->tracking_number ?: $order->tracking_number,
            'printed_at' => $order->printed_at?->toIso8601String(),
            'warehouse_id' => $order->warehouse_id,
            'warehouse_name' => $order->warehouse?->name,
            'shipping_provider' => $order->shipping_provider,
            'shipping_method' => $order->shipping_method,
            'province' => $province,
            'district' => (string) ($geo['district'] ?? ''),
            'ward' => (string) ($geo['ward'] ?? ''),
            'receiver_name' => $receiverName,
            'receiver_phone' => $receiverPhone,
            'address' => $address,
            'sender_name' => $order->warehouse?->name ?: 'Kho',
            'sender_phone' => $order->warehouse?->phone,
            'sender_print_note' => $senderNote,
            'sale_name' => $order->saleUser?->name,
            'sale_phone' => $order->saleUser?->phone,
            'shipping_fee' => (int) $order->shipping_fee_collected,
            'discount' => (int) $order->discount,
            'deposit' => (int) $order->deposit,
            'cod' => $cod,
            'total' => (int) $order->total,
            'shipping_notes' => (string) $order->shipping_notes,
            'customer_note' => (string) $order->customer_note,
            'can_print_carrier_label' => (bool) ($shipment?->tracking_number || $order->tracking_number),
            'label_provider' => $order->shipping_provider
                ?: (is_array($profile['providers'] ?? null) ? ($profile['providers'][0] ?? null) : null),
            'products' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->product?->sku,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
                'discount_amount' => (int) ($item->discount_amount ?? 0),
                'item_type' => $item->item_type,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentProfile(array $profile): array
    {
        return [
            'key' => $profile['key'],
            'title' => $profile['title'],
            'ui' => $profile['ui'],
            'tone' => $profile['tone'] ?? 'success',
            'max_quantity' => (int) ($profile['max_quantity'] ?? 2000),
            'templates' => $profile['templates'] ?? [],
            'sort_options' => $profile['sort_options'] ?? [],
            'tabs' => $profile['tabs'] ?? [],
            'toggles' => $profile['toggles'] ?? [],
            'sizes' => $profile['sizes'] ?? [],
            'orientations' => $profile['orientations'] ?? [],
            'supports_size' => (bool) ($profile['supports_size'] ?? false),
            'pretty_print' => (bool) ($profile['pretty_print'] ?? false),
            'group_by_warehouse' => (bool) ($profile['group_by_warehouse'] ?? false),
            'merge_all_label' => $profile['merge_all_label'] ?? 'Gộp tất cả đơn',
            'providers' => $profile['providers'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function defaultSettings(array $profile): array
    {
        $toggles = [];
        foreach ($profile['toggles'] ?? [] as $toggle) {
            $toggles[$toggle['key']] = (bool) ($toggle['default'] ?? false);
        }

        return [
            'quantity' => null,
            'template' => $profile['templates'][0]['value'] ?? null,
            'sort_by' => $profile['sort_options'][0]['value'] ?? 'closed_at',
            'height' => 0,
            'font_order_code' => 11,
            'font_barcode' => 11,
            'font_product' => 12,
            'font_support_code' => 32,
            'qr_size' => 70,
            'font_note' => 12,
            'font_footer' => 12,
            'sender_text' => $this->featureSettings->string('SettingDangDonNguoiGui', ''),
            'note_text' => '',
            'footer_text' => '',
            'size' => $profile['sizes'][0]['value'] ?? null,
            'orientation' => $profile['orientations'][0]['value'] ?? null,
            'pretty_print' => false,
            'tab' => $profile['tabs'][0]['value'] ?? 'label',
            'toggles' => $toggles,
        ];
    }
}
