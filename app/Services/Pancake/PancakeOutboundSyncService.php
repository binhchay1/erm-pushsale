<?php

namespace App\Services\Pancake;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\PancakeSyncRecord;
use App\Models\Product;
use App\Models\WarehouseInventoryMovement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PancakeOutboundSyncService
{
    public function __construct(
        protected PancakeConnectionResolver $connections,
    ) {}

    /**
     * Đồng bộ mã vận đơn & trạng thái giao hàng từ Pushsale ngược về Pancake POS.
     *
     * @return array<string, mixed>|null
     */
    public function syncOrderShipping(Order $order): ?array
    {
        $syncRecord = PancakeSyncRecord::query()
            ->where('order_id', $order->id)
            ->where('external_type', PancakeSyncRecord::TYPE_ORDER)
            ->latest('id')
            ->first();

        if (! $syncRecord || ! filled($syncRecord->external_id)) {
            return null;
        }

        try {
            $connection = $this->connections->connection();
            if (! $connection->is_enabled || empty($connection->credentials['api_key'])) {
                return null;
            }

            $client = $this->connections->client();
            $tracking = (string) ($order->tracking_number ?: '');
            $carrier = (string) ($order->shipping_provider ?: $order->carrier_name ?: $order->shipping_method ?: '');

            $payload = [
                'partner_order_id' => $tracking,
                'bill_code' => $tracking,
                'tracking_number' => $tracking,
                'shipping_partner' => $carrier,
            ];

            if ($order->delivery_status) {
                $payload['shipping_status'] = (string) $order->delivery_status;
                $pancakeStatus = $this->mapDeliveryStatusToPancake($order->delivery_status);
                if ($pancakeStatus !== null) {
                    $payload['status'] = $pancakeStatus;
                }
            }

            if ($order->carrier_service_fee) {
                $payload['partner_fee'] = (int) $order->carrier_service_fee;
            }

            $res = $client->updateOrder($syncRecord->external_id, $payload);

            $syncRecord->update([
                'last_synced_at' => now(),
                'metadata' => array_merge($syncRecord->metadata ?? [], [
                    'last_outbound_sync' => [
                        'synced_at' => now()->toISOString(),
                        'tracking_number' => $tracking,
                        'delivery_status' => $order->delivery_status,
                    ],
                ]),
            ]);

            Log::info('[Pancake Sync] Đã đồng bộ vận đơn sang Pancake POS', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'pancake_order_id' => $syncRecord->external_id,
                'tracking_number' => $tracking,
            ]);

            return $res;
        } catch (Throwable $e) {
            Log::warning('[Pancake Sync] Lỗi khi đồng bộ vận đơn sang Pancake POS', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Đồng bộ tồn kho sang Pancake POS khi có biến động kho Pushsale.
     *
     * @return array<string, mixed>|null
     */
    public function syncInventoryMovement(WarehouseInventoryMovement $movement): ?array
    {
        try {
            $connection = $this->connections->connection();
            if (! $connection->is_enabled || empty($connection->credentials['api_key'])) {
                return null;
            }

            $product = $movement->product_id
                ? Product::query()->find($movement->product_id)
                : null;

            if (! $product || ! filled($product->sku)) {
                return null;
            }

            $client = $this->connections->client();
            $productsResp = $client->products(['search' => $product->sku]);
            $variations = Arr::get($productsResp, 'data')
                ?? Arr::get($productsResp, 'variations')
                ?? Arr::get($productsResp, 'products')
                ?? [];

            if (! is_array($variations) || empty($variations)) {
                return null;
            }

            $matchedVariation = null;
            foreach ($variations as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $sku = Arr::get($item, 'product_display_id')
                    ?? Arr::get($item, 'sku')
                    ?? Arr::get($item, 'variation_id')
                    ?? Arr::get($item, 'code');

                if (strcasecmp((string) $sku, (string) $product->sku) === 0) {
                    $matchedVariation = $item;
                    break;
                }
            }

            $variationId = Arr::get($matchedVariation, 'id') ?? Arr::get($variations, '0.id');
            if (! $variationId) {
                return null;
            }

            $res = $client->updateVariationQuantity((string) $variationId, (int) $movement->stock_after);

            Log::info('[Pancake Sync] Đã đồng bộ tồn kho sang Pancake POS', [
                'product_sku' => $product->sku,
                'pancake_variation_id' => $variationId,
                'stock' => $movement->stock_after,
            ]);

            return $res;
        } catch (Throwable $e) {
            Log::warning('[Pancake Sync] Lỗi khi đồng bộ tồn kho sang Pancake POS', [
                'movement_id' => $movement->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Map DeliveryStatus nội bộ sang mã status Pancake POS.
     * Pancake POS status codes tiêu chuẩn:
     * 1: Mới tạo / Chờ xử lý
     * 2: Đang giao hàng
     * 3: Hoàn thành / Đã giao
     * 4: Trả hàng / Đang hoàn
     * 5: Đã huỷ
     */
    protected function mapDeliveryStatusToPancake(mixed $deliveryStatus): ?int
    {
        $statusStr = $deliveryStatus instanceof DeliveryStatus ? $deliveryStatus->value : (string) $deliveryStatus;

        return match ($statusStr) {
            'picking_up', 'delivering', 'deliver_now' => 2,
            'delivered' => 3,
            'returning', 'return_completed' => 4,
            'cancelled' => 5,
            default => null,
        };
    }
}
