<?php

namespace App\Services\Shipping\Support;

use App\Models\Order;

class ShippingAddressHelper
{
    /** @return array{province: string, district: string, ward: string, hamlet: string, address: string} */
    public static function deliveryForOrder(Order $order): array
    {
        $geo = is_array($order->shipping_geo) ? $order->shipping_geo : [];

        return [
            'province' => (string) ($geo['province'] ?? config('shipping_partners.default_geo.province')),
            'district' => (string) ($geo['district'] ?? config('shipping_partners.default_geo.district')),
            'ward' => (string) ($geo['ward'] ?? config('shipping_partners.default_geo.ward')),
            'hamlet' => (string) ($geo['hamlet'] ?? 'Khác'),
            'address' => (string) ($geo['address'] ?? $order->shipping_address ?? 'Chưa cập nhật địa chỉ'),
        ];
    }

    /** @return array{pick_name: string, pick_tel: string, pick_address: string, pick_province: string, pick_district: string, pick_ward: ?string, pick_address_id: ?string} */
    public static function pickupForOrder(Order $order, PartnerCredentialResolver $credentials, string $provider): array
    {
        $warehouse = $order->warehouse;
        $creds = $credentials->credentials($provider)['credentials'];

        return [
            'pick_name' => $warehouse?->name ?? config('shipping_partners.pickup.name'),
            'pick_tel' => $warehouse?->phone ?? config('shipping_partners.pickup.tel'),
            'pick_address' => $warehouse?->address ?? config('shipping_partners.pickup.address'),
            'pick_province' => $warehouse?->pick_province ?? config('shipping_partners.pickup.province'),
            'pick_district' => $warehouse?->pick_district ?? config('shipping_partners.pickup.district'),
            'pick_ward' => $warehouse?->pick_ward ?? config('shipping_partners.pickup.ward'),
            'pick_address_id' => $warehouse?->ghtk_pick_address_id ?? ($creds['pick_address_id'] ?? null),
        ];
    }

    public static function totalWeightGrams(Order $order): int
    {
        if ($order->items->isEmpty()) {
            return 500;
        }

        $kg = max(0.1, $order->items->sum(fn ($item) => max(0.1, 0.2 * max(1, (int) $item->quantity))));

        return max(100, (int) round($kg * 1000));
    }

    public static function codAmount(Order $order): int
    {
        return max(0, (int) ($order->amount_to_collect ?: ($order->total - $order->deposit)));
    }

    public static function declaredValue(Order $order): int
    {
        return max(0, (int) ($order->total ?: $order->subtotal));
    }
}
