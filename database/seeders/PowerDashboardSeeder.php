<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Làm giàu dữ liệu demo cho menu 8.5.9 Power dashboard.
 * Không tạo luồng giả riêng; seeder chỉ phân bổ lại ngày/trạng thái trên các đơn đã sinh từ lead/manual/landing
 * để dashboard có đủ 12 ngày so sánh, gồm cả upsale, chốt đơn, giao hàng và CSKH.
 */
class PowerDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::query()
            ->with('items:id,order_id,quantity,total,unit_price')
            ->latest('id')
            ->limit(240)
            ->get()
            ->values();

        if ($orders->isEmpty()) {
            $this->command?->warn('Bỏ qua PowerDashboardSeeder: chưa có đơn để phân bổ dashboard.');
            return;
        }

        $warehouseUsers = User::query()->where('role', User::ROLE_WAREHOUSE)->orderBy('id')->get()->values();
        $deliveryStatuses = ['pending', 'shipping', 'delivered', 'delivered', 'delivered', 'returning', 'returned', 'cancelled'];
        $base = now()->startOfDay();

        foreach ($orders as $index => $order) {
            $offset = $index % 12;
            $date = $base->copy()->subDays($offset)->addHours(8 + ($index % 10))->addMinutes(($index * 7) % 50);
            $closed = $index % 5 !== 0;
            $status = $deliveryStatuses[$index % count($deliveryStatuses)];
            $warehouseUser = $warehouseUsers->isNotEmpty() ? $warehouseUsers[$index % $warehouseUsers->count()] : null;
            $shippingCost = 10000 + (($index % 5) * 5000);
            $codFee = 5000 + (($index % 4) * 2500);

            $order->forceFill([
                'data_arrived_at' => $date,
                'assigned_at' => $date->copy()->addMinutes(10),
                'closed_at' => $closed ? $date->copy()->addHours(1 + ($index % 5)) : null,
                'last_delivery_event_at' => $closed ? $date->copy()->addHours(8 + ($index % 8)) : null,
                'delivery_status' => $closed ? $status : 'pending',
                'warehouse_care_user_id' => $warehouseUser?->id,
                'carrier_service_fee' => $closed ? $shippingCost : 0,
                'cod_fee' => $closed ? $codFee : 0,
                'shipping_support_fee' => $closed && $index % 6 === 0 ? 5000 : 0,
                'carrier_return_fee' => in_array($status, ['returning', 'returned'], true) ? 12000 : 0,
                'is_returning_customer' => $index % 7 === 0,
                'contact_count' => 1,
            ])->save();
        }

        $this->command?->info('Đã phân bổ dữ liệu demo 12 ngày cho Power dashboard 8.5.9.');
    }
}
