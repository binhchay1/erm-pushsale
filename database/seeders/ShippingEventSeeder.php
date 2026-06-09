<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Models\FailedPartnerOrder;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Sự kiện webhook từ hãng vận chuyển — sinh từ chính các đơn đã giao/hoàn
 * để màn Đối soát vận chuyển của kế toán khớp với dữ liệu đơn hàng.
 */
class ShippingEventSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::query()
            ->whereNotNull('tracking_number')
            ->whereIn('delivery_status', [
                DeliveryStatus::Delivered->value,
                DeliveryStatus::Paid->value,
                DeliveryStatus::Returned->value,
            ])
            ->orderBy('id')
            ->get();

        foreach ($orders as $index => $order) {
            $isReturn = $order->delivery_status === DeliveryStatus::Returned->value;
            $systemCod = (int) $order->amount_to_collect;

            // ~5% đơn bị hãng báo lệch COD để có dữ liệu đối soát cần xử lý
            $mismatch = $index % 20 === 0 && ! $isReturn;
            $partnerCod = $mismatch ? max(0, $systemCod - 50_000) : ($isReturn ? 0 : $systemCod);

            ShippingWebhookEvent::query()->create([
                'provider' => 'viettel_post',
                'event_type' => 'status_update',
                'partner_order_code' => $order->order_code,
                'tracking_number' => $order->tracking_number,
                'raw_status' => $isReturn ? 'GBV_RETURN' : 'GBV_SUCCESS',
                'mapped_status' => $order->delivery_status,
                'partner_cod' => $partnerCod,
                'system_cod' => $systemCod,
                'is_cod_mismatch' => $mismatch,
                'order_id' => $order->id,
                'payload' => ['seed' => true],
                'received_at' => $order->inventory_deducted_at?->copy()->addDays(2) ?? now()->subDays(1),
            ]);
        }

        // Webhook không khớp được đơn nào — cần kế toán kiểm tra thủ công
        foreach ([['VT99000001', 'PS-UNKNOWN-01'], ['VT99000002', 'PS-UNKNOWN-02']] as $i => [$tracking, $code]) {
            ShippingWebhookEvent::query()->create([
                'provider' => 'viettel_post',
                'event_type' => 'status_update',
                'partner_order_code' => $code,
                'tracking_number' => $tracking,
                'raw_status' => 'GBV_SUCCESS',
                'mapped_status' => DeliveryStatus::Delivered->value,
                'partner_cod' => 450_000,
                'system_cod' => null,
                'is_cod_mismatch' => false,
                'order_id' => null,
                'payload' => ['seed' => true],
                'received_at' => now()->subDays($i + 1),
            ]);
        }

        $warehouse = Warehouse::query()->orderBy('id')->first();

        FailedPartnerOrder::query()->create([
            'platform' => 'TikTok',
            'partner_order_id' => 'TT-ERR-00001',
            'warehouse_id' => $warehouse?->id,
            'shop_name' => 'Shop chính hãng',
            'error_description' => 'Mã đơn không khớp với kho xuất',
        ]);

        FailedPartnerOrder::query()->create([
            'platform' => 'Shopee',
            'partner_order_id' => 'SP-ERR-00002',
            'warehouse_id' => $warehouse?->id,
            'shop_name' => 'Gian hàng demo',
            'error_description' => 'Thiếu thông tin người nhận khi đồng bộ',
        ]);

        $this->command?->info('Đã tạo '.ShippingWebhookEvent::query()->count().' sự kiện đối soát vận chuyển.');
    }
}
