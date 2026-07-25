<?php

namespace Database\Seeders;

use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Làm giàu dữ liệu demo cho các báo cáo quản trị 8.5.
 * Dữ liệu vẫn dùng bảng nghiệp vụ thật: orders/order_items/users/marketing_sources/warehouses.
 */
class PowerDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureBaseData();

        $orders = Order::query()
            ->with('items:id,order_id,quantity,unit_price,discount_amount,item_type')
            ->latest('id')
            ->limit(360)
            ->get()
            ->values();

        if ($orders->isEmpty()) {
            $this->command?->warn('Bỏ qua PowerDashboardSeeder: không tạo được đơn demo.');
            return;
        }

        $warehouseUsers = User::query()->where('role', User::ROLE_WAREHOUSE)->orderBy('id')->get()->values();
        $salesUsers = User::query()->where('role', User::ROLE_SALES)->orderBy('id')->get()->values();
        $marketingUsers = User::query()->where('role', User::ROLE_MARKETING)->orderBy('id')->get()->values();
        $sources = MarketingSource::query()->orderBy('id')->get()->values();
        $warehouses = Warehouse::query()->orderBy('id')->get()->values();
        $deliveryStatuses = ['pending', 'shipping', 'delivered', 'delivered', 'delivered', 'returning', 'returned', 'cancelled'];
        $base = now()->startOfDay();
        $repeatPhones = collect(range(1, 48))->map(fn (int $i): string => '09'.str_pad((string) (8800000 + $i), 8, '0', STR_PAD_LEFT))->values();

        foreach ($orders as $index => $order) {
            $offset = $index % 12;
            $date = $base->copy()->subDays($offset)->addHours(8 + ($index % 10))->addMinutes(($index * 7) % 50);
            $closed = $index % 5 !== 0;
            $status = $deliveryStatuses[$index % count($deliveryStatuses)];
            $warehouseUser = $warehouseUsers->isNotEmpty() ? $warehouseUsers[$index % $warehouseUsers->count()] : null;
            $saleUser = $salesUsers->isNotEmpty() ? $salesUsers[$index % $salesUsers->count()] : null;
            $marketingUser = $marketingUsers->isNotEmpty() ? $marketingUsers[$index % $marketingUsers->count()] : null;
            $source = $sources->isNotEmpty() ? $sources[$index % $sources->count()] : null;
            $warehouse = $warehouses->isNotEmpty() ? $warehouses[$index % $warehouses->count()] : null;
            $shippingCost = 10000 + (($index % 5) * 5000);
            $codFee = 5000 + (($index % 4) * 2500);
            $phone = $repeatPhones[$index % $repeatPhones->count()];
            $isReturning = $index >= $repeatPhones->count();

            $order->forceFill([
                'sale_user_id' => $order->sale_user_id ?: $saleUser?->id,
                'marketer_user_id' => $order->marketer_user_id ?: $marketingUser?->id,
                'marketing_source_id' => $order->marketing_source_id ?: $source?->id,
                'warehouse_id' => $order->warehouse_id ?: $warehouse?->id,
                'customer_phone' => $phone,
                'customer_name' => $order->customer_name ?: 'Khách demo '.$phone,
                'data_arrived_at' => $date,
                'assigned_at' => $date->copy()->addMinutes(10),
                'closed_at' => $closed ? $date->copy()->addHours(1 + ($index % 5)) : null,
                'last_delivery_event_at' => $closed ? $date->copy()->addHours(8 + ($index % 8)) : null,
                'delivery_status' => $closed ? $status : 'pending',
                'operation_stage' => $closed ? 'call_'.(($index % 6) + 1) : 'call_1',
                'operation_result' => $closed ? 'closed' : ($index % 3 === 0 ? 'call_later' : null),
                'next_operation_at' => ! $closed ? $date->copy()->addDay() : null,
                'warehouse_care_user_id' => $warehouseUser?->id,
                'carrier_service_fee' => $closed ? $shippingCost : 0,
                'cod_fee' => $closed ? $codFee : 0,
                'shipping_support_fee' => $closed && $index % 6 === 0 ? 5000 : 0,
                'carrier_return_fee' => in_array($status, ['returning', 'returned'], true) ? 12000 : 0,
                'is_returning_customer' => $isReturning,
                'is_duplicate_phone' => $index % 17 === 0,
                'contact_count' => 1,
            ])->save();

            $this->normalizeOrderItems($order, $index);
            $this->recalculateOrderTotal($order);
        }

        $this->command?->info('Đã phân bổ dữ liệu demo 12 ngày cho Power dashboard và nhóm báo cáo 8.5.');
    }

    private function ensureBaseData(): void
    {
        $team = Team::query()->firstOrCreate(['name' => 'Nhóm Demo Dashboard'], ['type' => 'sale']);
        $warehouse = Warehouse::query()->firstOrCreate(['name' => 'Kho Demo Dashboard'], ['code' => 'KDD', 'address' => 'Hà Nội']);
        $product = Product::query()->firstOrCreate(
            ['sku' => 'PWR-DASH-DEMO'],
            ['name' => 'Combo demo Power Dashboard', 'unit_price' => 299000, 'cost_price' => 120000, 'is_active' => true]
        );

        foreach ([
            [User::ROLE_SALES, 'sale.demo.', 'Sale Demo '],
            [User::ROLE_MARKETING, 'mkt.demo.', 'Marketing Demo '],
            [User::ROLE_WAREHOUSE, 'warehouse.demo.', 'Kho Demo '],
        ] as [$role, $emailPrefix, $namePrefix]) {
            for ($i = 1; $i <= 6; $i++) {
                User::query()->firstOrCreate(
                    ['email' => $emailPrefix.$i.'@saleops.local'],
                    [
                        'name' => $namePrefix.$i,
                        'password' => Hash::make('password'),
                        'role' => $role,
                        'team_id' => $role === User::ROLE_SALES ? $team->id : null,
                    ]
                );
            }
        }

        $marketer = User::query()->where('role', User::ROLE_MARKETING)->first();
        for ($i = 1; $i <= 6; $i++) {
            MarketingSource::query()->firstOrCreate(
                ['name' => 'Nguồn dashboard demo '.$i],
                ['ad_channel' => 'landing', 'budget' => 12000000 + $i * 1500000, 'marketer_user_id' => $marketer?->id, 'is_active' => true]
            );
        }

        if (Order::query()->count() > 0) {
            return;
        }

        $sales = User::query()->where('role', User::ROLE_SALES)->get()->values();
        $marketers = User::query()->where('role', User::ROLE_MARKETING)->get()->values();
        $sources = MarketingSource::query()->get()->values();
        for ($i = 0; $i < 180; $i++) {
            $price = 199000 + (($i % 5) * 50000);
            $order = Order::query()->create([
                'order_code' => 'PWR'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'sale_user_id' => $sales->isNotEmpty() ? $sales[$i % $sales->count()]->id : null,
                'marketer_user_id' => $marketers->isNotEmpty() ? $marketers[$i % $marketers->count()]->id : null,
                'team_id' => $team->id,
                'marketing_source_id' => $sources->isNotEmpty() ? $sources[$i % $sources->count()]->id : null,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'customer_name' => 'Khách demo báo cáo '.($i + 1),
                'customer_phone' => '09'.str_pad((string) (7700000 + $i), 8, '0', STR_PAD_LEFT),
                'shipping_address' => 'Địa chỉ demo '.($i + 1),
                'subtotal' => $price,
                'total' => $price,
                'amount_to_collect' => $price,
                'delivery_status' => 'pending',
                'contact_count' => 1,
            ]);
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'item_type' => $i % 9 === 0 ? 'upsell' : 'product',
                'origin' => $i % 9 === 0 ? 'upsell' : 'landing',
                'quantity' => 1 + ($i % 4),
                'unit_price' => $price,
                'cost_price' => 120000,
                'discount_amount' => $i % 7 === 0 ? 20000 : 0,
                'meta' => ['demo' => true, 'seed' => 'power_dashboard'],
            ]);
        }
    }

    private function normalizeOrderItems(Order $order, int $index): void
    {
        $items = $order->items;
        if ($items->isEmpty()) {
            $product = Product::query()->first();
            if (! $product) return;
            $items = collect([OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'item_type' => 'product',
                'origin' => 'landing',
                'quantity' => 1,
                'unit_price' => (int) ($product->unit_price ?? 199000),
                'cost_price' => (int) ($product->cost_price ?? 0),
                'discount_amount' => 0,
            ])]);
        }

        $targetQty = ($index % 30) + 1;
        $first = $items->first();
        if ($first) {
            $first->forceFill([
                'quantity' => $targetQty,
                'item_type' => $index % 8 === 0 ? 'upsell' : ($first->item_type ?: 'product'),
                'origin' => $index % 8 === 0 ? 'upsell' : ($first->origin ?: 'landing'),
            ])->save();
        }
    }

    private function recalculateOrderTotal(Order $order): void
    {
        $order->loadMissing('items');
        $subtotal = (int) $order->items->sum(fn (OrderItem $item): int => (int) $item->unit_price * (int) $item->quantity);
        $discount = (int) $order->items->sum('discount_amount');
        $shippingFee = (int) ($order->shipping_fee_collected ?? 0);
        $vat = (int) ($order->vat ?? 0);
        $total = max(0, $subtotal - $discount + $shippingFee + $vat);
        $order->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'amount_to_collect' => max(0, $total - (int) ($order->deposit ?? 0)),
        ])->save();
    }
}
