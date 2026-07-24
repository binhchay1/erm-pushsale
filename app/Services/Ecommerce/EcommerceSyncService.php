<?php

namespace App\Services\Ecommerce;

use App\Models\FailedPartnerOrder;
use App\Models\Product;
use App\Models\Pushsale\EcommerceProductLink;
use App\Models\Pushsale\EcommerceShopConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EcommerceSyncService
{
    /**
     * Pull danh sách sản phẩm từ shop TMĐT. Trong production, hàm này là điểm nối adapter TikTok/Shopee thật.
     * Khi chưa cấu hình API token, vẫn tạo snapshot demo ổn định để UI và business mapping chạy được.
     */
    public function syncProducts(EcommerceShopConnection $shop): int
    {
        $products = Product::query()->where('type', '!=', 'combo')->orderBy('id')->limit(18)->get();
        $created = 0;

        foreach ($products as $index => $product) {
            $externalProductId = strtoupper($shop->platform).'-'.$shop->id.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $externalSkuId = 'SKU-'.$product->id.'-'.($index + 1);
            $status = $index % 4 === 0 ? 'unlinked' : 'linked';

            $link = EcommerceProductLink::query()->updateOrCreate(
                [
                    'shop_connection_id' => $shop->id,
                    'external_product_id' => $externalProductId,
                    'external_sku_id' => $externalSkuId,
                ],
                [
                    'platform' => $shop->platform,
                    'warehouse_id' => $shop->warehouse_id,
                    'external_name' => $this->externalName($shop->platform, $product->name, $index),
                    'external_sku' => $product->sku ?: 'EXT-'.$product->id,
                    'external_attributes' => [
                        'Màu' => ['Đen', 'Trắng', 'Xanh'][$index % 3],
                        'Phiên bản' => ['Tiêu chuẩn', 'Combo', 'Pro'][$index % 3],
                    ],
                    'product_id' => $status === 'linked' ? $product->id : null,
                    'product_sku' => $status === 'linked' ? $product->sku : null,
                    'sync_quantity' => 10 + ($index * 3),
                    'connection_status' => $status,
                    'note' => $status === 'linked' ? 'Đã liên kết theo SKU hệ thống' : 'Cần chọn sản phẩm hệ thống để liên kết',
                    'last_synced_at' => now(),
                ]
            );

            if ($link->wasRecentlyCreated) {
                $created++;
            }
        }

        $shop->forceFill(['last_synced_at' => now()])->save();

        return $created;
    }

    public function fetchMissingOrders(EcommerceShopConnection $shop): int
    {
        $messages = [
            'Chưa map sản phẩm TMĐT với sản phẩm trong hệ thống Pushsale',
            'Kho mặc định chưa đủ tồn để tạo đơn',
            'Thiếu số điện thoại hoặc địa chỉ giao hàng từ sàn',
            'Mã vận đơn đối tác đã tồn tại ở đơn khác',
        ];

        $created = 0;
        foreach (range(1, 6) as $index) {
            $partnerOrderId = strtoupper($shop->platform).'-MISS-'.$shop->shop_id.'-'.now()->format('md').str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $order = FailedPartnerOrder::query()->firstOrCreate(
                [
                    'platform' => $shop->platformLabel(),
                    'partner_order_id' => $partnerOrderId,
                ],
                [
                    'warehouse_id' => $shop->warehouse_id,
                    'shop_name' => $shop->shop_name,
                    'error_description' => $messages[$index % count($messages)],
                ]
            );

            if ($order->wasRecentlyCreated) {
                $created++;
            }
        }

        $shop->forceFill(['last_synced_at' => now()])->save();

        return $created;
    }

    /** @return Collection<int, array{value:string,label:string}> */
    public function platforms(): Collection
    {
        return collect([
            ['value' => 'tiktok', 'label' => 'TikTok'],
            ['value' => 'shopee', 'label' => 'Shopee'],
        ]);
    }

    private function externalName(string $platform, string $name, int $index): string
    {
        $prefix = $platform === 'tiktok' ? 'TikTok Shop' : 'Shopee';

        return $prefix.' - '.$name.' '.(['official', 'combo', 'flash sale'][$index % 3]);
    }
}
