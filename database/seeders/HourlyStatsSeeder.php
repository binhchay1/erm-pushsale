<?php

namespace Database\Seeders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu demo riêng cho menu 8.1.1 - Biểu đồ thống kê theo khung giờ.
 *
 * Vẫn đi qua bảng nghiệp vụ thật orders/order_items/users/products/sources.
 * Các đơn được rải 0h-23h trong ngày hiện tại để test đủ heatmap tỷ lệ chốt,
 * số chốt đơn và số contact giống layout Pushsale gốc.
 */
class HourlyStatsSeeder extends Seeder
{
    /** @var array<int, int> */
    private array $contactsByHour = [25, 12, 10, 12, 25, 39, 47, 63, 49, 59, 70, 40, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

    /** @var array<int, int> */
    private array $closedByHour = [14, 8, 5, 6, 18, 25, 33, 38, 28, 41, 57, 21, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

    public function run(): void
    {
        Order::query()->where('order_code', 'like', 'HST%')->get(['id'])->each(function (Order $order): void {
            OrderItem::query()->where('order_id', $order->id)->delete();
            $order->delete();
        });

        $team = Team::query()->where('type', 'sale')->orderBy('id')->first()
            ?? Team::query()->firstOrCreate(['name' => 'Nhóm Sale A'], ['type' => 'sale']);
        $warehouse = Warehouse::query()->orderBy('id')->first()
            ?? Warehouse::query()->firstOrCreate(['name' => 'Kho Hồ Chí Minh'], ['code' => 'HCM']);
        $sales = User::query()->where('role', User::ROLE_SALES)->orderBy('id')->get()->values();
        $marketers = User::query()->where('role', User::ROLE_MARKETING)->orderBy('id')->get()->values();
        $sources = MarketingSource::query()->orderBy('id')->get()->values();
        $products = Product::query()->where('type', 'product')->orderBy('id')->get()->values();

        if ($sales->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('Bỏ qua HourlyStatsSeeder: thiếu sale hoặc sản phẩm demo.');
            return;
        }

        $base = now()->startOfDay();
        $seq = 0;

        foreach ($this->contactsByHour as $hour => $contactCount) {
            $closedCount = $this->closedByHour[$hour] ?? 0;

            for ($i = 0; $i < $contactCount; $i++) {
                $seq++;
                $sale = $sales[$seq % $sales->count()];
                $marketer = $marketers->isNotEmpty() ? $marketers[$seq % $marketers->count()] : null;
                $source = $sources->isNotEmpty() ? $sources[$seq % $sources->count()] : null;
                $product = $products[$seq % $products->count()];
                $arrivedAt = $base->copy()->setTime($hour, ($i * 7) % 60, 0);
                $isClosed = $i < $closedCount;
                $quantity = 1 + ($seq % 3 === 0 ? 1 : 0);
                $unitPrice = max(1000, (int) ($product->unit_price ?: 199000));
                $subtotal = $quantity * $unitPrice;
                $discount = $seq % 7 === 0 ? (int) round($subtotal * 0.05) : 0;
                $total = max(0, $subtotal - $discount);

                $order = Order::query()->create([
                    'order_code' => 'HST'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                    'sale_user_id' => $sale->id,
                    'marketer_user_id' => $marketer?->id,
                    'team_id' => $sale->team_id ?: $team->id,
                    'marketing_source_id' => $source?->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'customer_name' => 'Khách giờ '.$hour.'-'.$i,
                    'customer_phone' => '097'.str_pad((string) (5000000 + $seq), 7, '0', STR_PAD_LEFT),
                    'shipping_address' => 'Địa chỉ demo khung giờ '.$hour,
                    'data_arrived_at' => $arrivedAt,
                    'assigned_at' => $arrivedAt->copy()->addMinutes(3 + ($i % 12)),
                    'closed_at' => $isClosed ? $arrivedAt->copy()->addMinutes(20 + ($i % 25)) : null,
                    'last_delivery_event_at' => $isClosed ? $arrivedAt->copy()->addHours(4) : null,
                    'operation_stage' => $isClosed ? 'care_1' : 'call_2',
                    'operation_result' => $isClosed ? 'closed_success' : 'callback_scheduled',
                    'closing_status' => $isClosed ? ClosingStatus::Closed->value : ClosingStatus::Open->value,
                    'delivery_status' => $isClosed ? ($seq % 5 === 0 ? DeliveryStatus::Delivered->value : DeliveryStatus::Paid->value) : DeliveryStatus::DeliverNow->value,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'vat' => 0,
                    'shipping_fee_collected' => 30000,
                    'total' => $total,
                    'deposit' => $isClosed && $seq % 9 === 0 ? 50000 : 0,
                    'amount_to_collect' => $isClosed ? max(0, $total - ($seq % 9 === 0 ? 50000 : 0)) : 0,
                    'carrier_service_fee' => $isClosed ? 15000 : 0,
                    'cod_fee' => $isClosed ? 5000 : 0,
                    'cod_support' => $isClosed ? 5000 : 0,
                    'contact_count' => 1,
                ]);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'item_type' => 'base',
                    'origin' => 'hourly-stats-demo',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                ]);

                if ($isClosed && $seq % 4 === 0 && $products->count() > 1) {
                    $upsell = $products[($seq + 1) % $products->count()];
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $upsell->id,
                        'product_name' => $upsell->name,
                        'item_type' => 'upsell',
                        'origin' => 'hourly-stats-upsale-demo',
                        'quantity' => 1,
                        'unit_price' => max(1000, (int) ($upsell->unit_price ?: 99000)),
                    ]);
                }
            }
        }

        $this->command?->info('Đã tạo dữ liệu demo theo khung giờ cho menu 8.1.1.');
    }
}
