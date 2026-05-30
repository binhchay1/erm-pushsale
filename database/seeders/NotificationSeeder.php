<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@saleops.local')->first();
        $sales = User::query()->where('email', 'sales@saleops.local')->first();

        if ($admin) {
            $samples = [
                ['lead', 'Lead mới từ landing', 'Nguyễn Văn A · 0987654321', '/admin/leads'],
                ['order', 'Đơn PS1800001 đã giao thành công', 'COD 920.000đ đã thu', '/admin/reports/business'],
                ['shipping', 'Lệch COD vận đơn VT123456', 'Đối tác báo 850.000đ / hệ thống 920.000đ', '/admin/shipping/reconciliation'],
                ['system', 'Chiến dịch "cam-mini-fb" đang chạy', 'Đã nhận 12 lead trong hôm nay', '/admin/marketing/campaigns'],
            ];

            foreach ($samples as $index => [$type, $title, $message, $url]) {
                UserNotification::query()->firstOrCreate(
                    ['user_id' => $admin->id, 'title' => $title],
                    [
                        'type' => $type,
                        'message' => $message,
                        'url' => $url,
                        'read_at' => $index >= 2 ? now() : null,
                    ],
                );
            }
        }

        if ($sales) {
            UserNotification::query()->firstOrCreate(
                ['user_id' => $sales->id, 'title' => 'Lead mới — Trần Minh Anh'],
                [
                    'type' => 'lead',
                    'message' => 'Facebook landing · 0912345678 · Khách mới',
                    'url' => '/sales/workspace',
                    'read_at' => null,
                ],
            );
        }
    }
}
