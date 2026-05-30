<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@saleops.local')->first();
        $sales = User::query()->where('role', UserRole::Sales)->get();
        $marketingUsers = User::query()->where('role', UserRole::Marketing)->get();
        $product = Product::query()->where('sku', 'SP292627')->first();
        $warehouse = Warehouse::query()->where('code', 'HB')->first();
        $saleTeam = Team::query()->where('name', 'Nhóm Sale A')->first();

        $sources = MarketingSource::query()->whereNull('parent_id')->get();
        $sourceParent = $sources->firstWhere('name', 'Hải - camera mini nhật bản') ?? $sources->first();
        $source2 = $sources->firstWhere('name', 'Ngọc Huyền - GG - Bột diệt cỏ') ?? $sources->last();

        if (! $product || ! $warehouse || ! $saleTeam || ! $sourceParent) {
            return;
        }

        $statuses = [
            DeliveryStatus::WaitingWaybill,
            DeliveryStatus::Delivering,
            DeliveryStatus::Delivered,
            DeliveryStatus::Paid,
            DeliveryStatus::Returned,
            DeliveryStatus::CancelWaybill,
        ];

        $stages = OperationStage::cases();
        $marketers = $marketingUsers->values();
        $marketerCount = $marketers->count();
        $i = 0;

        foreach ($sales->values() as $sIndex => $saleUser) {
            $orderCount = 3 + (($sIndex * 13 + 7) % 22);

            for ($n = 0; $n < $orderCount; $n++) {
                $i++;
                $status = $statuses[$i % count($statuses)];
                $stage = $stages[$i % count($stages)];
                $source = $i % 2 === 0 ? $sourceParent : $source2;

                if ($marketerCount > 0) {
                    $marketerIndex = $i % 3 === 0
                        ? $i % min(10, $marketerCount)
                        : ($i * 7) % $marketerCount;
                    $marketer = $marketers[$marketerIndex];
                } else {
                    $marketer = $admin;
                }

                $qty = 1 + ($i % 3);
                $unitPrice = $product->unit_price;
                $subtotal = $qty * $unitPrice;
                $closedAt = now()->subDays(($i * 7) % 95);
                $orderCode = 'PS'.str_pad((string) (1_800_000 + $i), 11, '0', STR_PAD_LEFT);

                $order = Order::query()->firstOrCreate(['order_code' => $orderCode], [
                    'sale_user_id' => $saleUser->id,
                    'marketer_user_id' => $marketer?->id,
                    'team_id' => $saleTeam->id,
                    'marketing_source_id' => $source->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'customer_name' => 'Khách hàng '.$i,
                    'customer_phone' => '09'.str_pad((string) (10000000 + ($i % 89999999)), 8, '0', STR_PAD_LEFT),
                    'phone_carrier' => 'VIETTEL',
                    'customer_note' => 'Ghi chú đơn mẫu #'.$i,
                    'shipping_address' => 'Hà Nội — địa chỉ demo '.$i,
                    'data_arrived_at' => $closedAt->copy()->subDays(2),
                    'assigned_at' => $closedAt->copy()->subDay(),
                    'closed_at' => $closedAt,
                    'operation_stage' => $stage->value,
                    'delivery_status' => $status->value,
                    'carrier_name' => 'Viettel Post(COD)',
                    'tracking_number' => 'VT'.str_pad((string) (100000 + ($i % 899999)), 6, '0', STR_PAD_LEFT),
                    'is_returning_customer' => $i % 3 === 0,
                    'subtotal' => $subtotal,
                    'discount' => (int) ($subtotal * 0.05),
                    'vat' => 0,
                    'shipping_fee_collected' => 30_000,
                    'total' => $subtotal + 30_000,
                    'deposit' => $i % 4 === 0 ? 100_000 : 0,
                    'amount_to_collect' => $subtotal + 30_000,
                    'contact_count' => 1,
                    'cod_fee' => 15_000,
                    'cod_support' => 5_000,
                ]);

                OrderItem::query()->firstOrCreate([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                ], [
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]);
            }
        }
    }
}
