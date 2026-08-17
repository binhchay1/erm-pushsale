<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Leads\LandingUpsellService;
use App\Services\Leads\LeadOrderFactory;
use App\Support\ActivityLogger;
use App\Support\ShippingProviders;
use App\Support\VietnamDivisions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sửa nội dung đơn từ màn tác nghiệp telesale: sản phẩm/combo, chiết khấu,
 * đơn vị vận chuyển. Doanh thu (total) được tính lại theo giá trị cuối đơn.
 */
class SaleOrderEditService
{
    public function __construct(
        private readonly LeadOrderFactory $factory,
        private readonly LandingUpsellService $landingUpsell,
        private readonly OrderOperationHistoryService $history,
        private readonly SalesVisibilityScope $visibility,
    ) {}

    /**
     * @param  array{items?: array<int, array<string, mixed>>, discount?: int|null, shipping_provider?: string|null, carrier_name?: string|null}  $payload
     */
    public function update(Order $order, User $actor, array $payload): Order
    {
        if (! $this->visibility->canOperateOrder($actor, $order)) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.no_permission_operate'),
            ]);
        }

        if (! SaleOperationPolicy::canChangeStatus($order)) {
            throw ValidationException::withMessages([
                'order' => __('messages.sale_ops.cannot_change_status'),
            ]);
        }

        $this->landingUpsell->lockFromSaleAction($order);

        return DB::transaction(function () use ($order, $actor, $payload) {
            $before = $this->history->snapshot($order);

            // Nguồn dữ liệu cố định với sale — chỉ Admin được đổi.
            if (array_key_exists('marketing_source_id', $payload) && $actor->isAdmin()) {
                $order->marketing_source_id = $payload['marketing_source_id'] ?: null;
            }

            if (array_key_exists('items', $payload) && is_array($payload['items'])) {
                $items = $payload['items'];
                if (! $actor->isAdmin()) {
                    $items = $this->lockUnitPricesForSale($items, $order);
                }
                $order->items()->delete();
                foreach ($this->factory->buildItemRows($items, 'telesale') as $row) {
                    $order->items()->create($row);
                }
            }

            // Thông tin khách hàng & giao hàng (chỉ cập nhật khi FE gửi lên).
            foreach ([
                'customer_name' => 'customer_name',
                'customer_phone' => 'customer_phone',
                'shipping_address' => 'shipping_address',
                'customer_note' => 'customer_note',
                'shipping_method' => 'shipping_method',
                'shipping_notes' => 'shipping_notes',
            ] as $key => $column) {
                if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                    $order->{$column} = $payload[$key];
                }
            }

            if (array_key_exists('warehouse_id', $payload)) {
                $order->warehouse_id = $payload['warehouse_id'] ?: null;
            }

            if (array_key_exists('shipping_fee_collected', $payload)) {
                $order->shipping_fee_collected = max(0, (int) ($payload['shipping_fee_collected'] ?? 0));
            }

            if (array_key_exists('deposit', $payload)) {
                $order->deposit = max(0, (int) ($payload['deposit'] ?? 0));
            }

            if (array_key_exists('vat', $payload)) {
                $order->vat = max(0, (int) ($payload['vat'] ?? 0));
            }

            if (array_key_exists('discount', $payload)) {
                $order->discount = max(0, (int) ($payload['discount'] ?? 0));
            }

            if (array_key_exists('shipping_provider', $payload)) {
                $provider = $payload['shipping_provider'] ?: null;
                $order->shipping_provider = $provider;
                // Đồng bộ tên hãng hiển thị theo provider, trừ khi FE gửi carrier_name riêng.
                if (empty($payload['carrier_name'])) {
                    $order->carrier_name = ShippingProviders::label($provider);
                }
            }

            if (! empty($payload['carrier_name'])) {
                $order->carrier_name = $payload['carrier_name'];
            }

            // Chạy sau khi đã set shipping_provider để lấy đúng nhãn dịch vụ vận chuyển.
            $this->applyDeliveryAddress($order, $payload);

            $order->save();

            $fresh = $this->factory->syncTotals($order->fresh(['items']));

            $metadata = [
                'changed_fields' => array_values(array_keys($payload)),
                'total' => (int) $fresh->total,
                'discount' => (int) $fresh->discount,
                'items' => $fresh->items->count(),
                'shipping_provider' => $fresh->shipping_provider,
            ];

            ActivityLogger::log(
                ActivityLogger::ORDER_UPDATED,
                $fresh,
                $metadata,
                $fresh->order_code ?? ('#'.$fresh->id),
                $actor,
            );

            $this->history->record(
                $fresh,
                $actor,
                OrderOperationHistory::ACTION_ORDER_UPDATED,
                $before,
                $this->history->snapshot($fresh),
                metadata: $metadata,
            );

            return $fresh->fresh(['items', 'saleUser', 'team', 'marketingSource', 'warehouse']);
        });
    }

    /**
     * Địa chỉ giao đã xác nhận: lưu cấu trúc Tỉnh/Huyện/Xã + số nhà vào shipping_geo,
     * đồng thời dựng shipping_address_2 (địa chỉ đầy đủ) để dùng cho vận chuyển/hiển thị.
     * Dịch vụ vận chuyển (shipping_service) lưu kèm trong shipping_geo.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyDeliveryAddress(Order $order, array $payload): void
    {
        // Người nhận hàng khác khách hàng.
        if (array_key_exists('receiver_is_customer', $payload)) {
            if ($payload['receiver_is_customer']) {
                $order->receiver_name = null;
                $order->receiver_phone = null;
            } else {
                if (array_key_exists('receiver_name', $payload)) {
                    $order->receiver_name = $payload['receiver_name'] ?: null;
                }
                if (array_key_exists('receiver_phone', $payload)) {
                    $order->receiver_phone = $payload['receiver_phone'] ?: null;
                }
            }
        }

        $geoKeys = ['province_code', 'district_code', 'ward_code', 'address_detail', 'shipping_service', 'address_mode'];
        $hasGeoInput = (bool) array_intersect($geoKeys, array_keys($payload));

        if (! $hasGeoInput) {
            return;
        }

        $geo = is_array($order->shipping_geo) ? $order->shipping_geo : [];

        $mode = ($payload['address_mode'] ?? null) === VietnamDivisions::MODE_NEW
            ? VietnamDivisions::MODE_NEW
            : VietnamDivisions::MODE_OLD;

        $provinceCode = $payload['province_code'] ?? null;
        $districtCode = $payload['district_code'] ?? null;
        $wardCode = $payload['ward_code'] ?? null;
        $detail = trim((string) ($payload['address_detail'] ?? ''));

        if ($mode === VietnamDivisions::MODE_NEW) {
            // 2 cấp: Tỉnh → Phường/Xã (không có Quận/Huyện).
            $districtCode = null;
            $districtName = null;
            $provinceName = $provinceCode ? VietnamDivisions::newProvinceName($provinceCode) : null;
            $wardName = ($provinceCode && $wardCode)
                ? VietnamDivisions::newWardName($provinceCode, $wardCode) : null;
        } else {
            $provinceName = $provinceCode ? VietnamDivisions::provinceName($provinceCode) : null;
            $districtName = ($provinceCode && $districtCode)
                ? VietnamDivisions::districtName($provinceCode, $districtCode) : null;
            $wardName = ($districtCode && $wardCode)
                ? VietnamDivisions::wardName($districtCode, $wardCode) : null;
        }

        $geo = array_merge($geo, [
            'mode' => $mode,
            'address' => $detail !== '' ? $detail : ($geo['address'] ?? null),
            'address_detail' => $detail,
            'province_code' => $provinceCode,
            'province' => $provinceName,
            'district_code' => $districtCode,
            'district' => $districtName,
            'ward_code' => $wardCode,
            'ward' => $wardName,
        ]);

        if (array_key_exists('shipping_service', $payload)) {
            $service = $payload['shipping_service'] ?: null;
            $geo['service_code'] = $service;
            $geo['service'] = \App\Support\ShippingProviders::serviceLabel($order->shipping_provider, $service);
        }

        $order->shipping_geo = $geo;

        // Dựng địa chỉ đầy đủ: số nhà, [Xã], [Huyện], Tỉnh.
        $full = collect([$detail, $wardName, $districtName, $provinceName])
            ->filter(fn ($p) => filled($p))
            ->implode(', ');

        $order->shipping_address_2 = $full !== '' ? $full : null;
    }

    /**
     * Sale không được tự ý đổi đơn giá — giữ giá catalog của sản phẩm.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function lockUnitPricesForSale(array $items, Order $order): array
    {
        $productIds = collect($items)
            ->map(fn (array $item) => (int) ($item['product_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $prices = $productIds === []
            ? collect()
            : \App\Models\Product::query()->whereIn('id', $productIds)->pluck('unit_price', 'id');

        // Dòng sản phẩm Ladi chưa map catalog: giữ đúng đơn giá đang lưu trên đơn.
        $landingPrices = $order->items()
            ->whereNull('product_id')
            ->pluck('unit_price', 'product_name');

        return array_map(function (array $item) use ($prices, $landingPrices): array {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0) {
                if ($prices->has($productId)) {
                    $item['unit_price'] = (int) $prices->get($productId);
                }

                return $item;
            }

            $name = (string) ($item['product_name'] ?? '');
            if ($landingPrices->has($name)) {
                $item['unit_price'] = (int) $landingPrices->get($name);
            }

            return $item;
        }, $items);
    }
}
