<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Pushsale\PhoneBlacklist;
use App\Models\User;
use Illuminate\Database\Seeder;

class PhoneBlacklistSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->first() ?? User::query()->first();
        $orders = Order::query()->latest('id')->limit(8)->get();
        $reasons = [
            'Khách báo không nhận hàng nhiều lần',
            'Bom hàng sau khi đã xác nhận',
            'Số nghi ngờ spam/chốt ảo',
            'Đã hoàn hàng nhiều lần, cần quản trị duyệt trước khi chốt',
            'Khách yêu cầu không liên hệ lại',
        ];

        foreach ($orders as $index => $order) {
            $phone = preg_replace('/\D+/', '', (string) $order->customer_phone);
            if ($phone === '') continue;
            PhoneBlacklist::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'reason' => $reasons[$index % count($reasons)],
                    'order_id' => $order->id,
                    'creation_type' => $index % 3 === 0 ? 'warehouse' : 'manual',
                    'created_by_user_id' => $admin?->id,
                    'updated_by_user_id' => $admin?->id,
                ],
            );
        }

        foreach (range(1, 12) as $i) {
            PhoneBlacklist::query()->updateOrCreate(
                ['phone' => '09888'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)],
                [
                    'reason' => $reasons[$i % count($reasons)],
                    'creation_type' => 'manual',
                    'created_by_user_id' => $admin?->id,
                    'updated_by_user_id' => $admin?->id,
                ],
            );
        }
    }
}
