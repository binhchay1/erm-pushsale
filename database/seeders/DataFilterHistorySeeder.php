<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Seeder;

class DataFilterHistorySeeder extends Seeder
{
    public function run(): void
    {
        ActivityLog::query()->where('action', ActivityLogger::DATA_FILTER_SEARCHED)->delete();

        $actors = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Sales, UserRole::Marketing, UserRole::Warehouse])
            ->orderBy('id')
            ->get()
            ->values();

        if ($actors->isEmpty()) {
            return;
        }

        $samples = [
            [
                'page_code' => '2.1.1',
                'page_title' => 'Hồ sơ khách hàng',
                'filter_label' => 'Hồ sơ khách hàng · Trạng thái chốt đơn: Chưa chốt đơn · Từ ngày: '.now()->subDays(7)->format('d/m/Y'),
                'date_type' => 'NgayTao',
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
                'closed_status' => '0',
                'closing_status' => 'Chưa chốt đơn',
                'closing_status_label' => 'Chưa chốt đơn',
                'delivery_status' => null,
                'delivery_status_label' => null,
                'result_count' => 18,
            ],
            [
                'page_code' => '2.2.1',
                'page_title' => 'Sale tác nghiệp',
                'filter_label' => 'Sale tác nghiệp · Trạng thái chốt đơn: Đã chốt đơn · Kiểu ngày: Ngày sale chốt đơn',
                'date_type' => 'DonHangNgayChot',
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
                'closed_status' => '1',
                'closing_status' => 'Đã chốt đơn',
                'closing_status_label' => 'Đã chốt đơn',
                'delivery_status' => '20',
                'delivery_status_label' => 'Đã đăng',
                'result_count' => 9,
            ],
            [
                'page_code' => '3.1.1',
                'page_title' => 'Tác nghiệp kho',
                'filter_label' => 'Tác nghiệp kho · Trạng thái giao hàng: Đang giao hàng',
                'date_type' => 'NgayCapNhatTrangThaiGiaoHang',
                'date_from' => now()->subDays(5)->toDateString(),
                'date_to' => now()->toDateString(),
                'closed_status' => '1',
                'closing_status' => 'Đã chốt đơn',
                'closing_status_label' => 'Đã chốt đơn',
                'delivery_status' => '30',
                'delivery_status_label' => 'Đang giao hàng',
                'result_count' => 12,
            ],
            [
                'page_code' => '2.4.1',
                'page_title' => 'Báo cáo sale',
                'filter_label' => 'Báo cáo sale · Từ khóa: khách cần gọi lại',
                'date_type' => 'SaleTacNghiepNgayCapNhat',
                'date_from' => now()->subDays(14)->toDateString(),
                'date_to' => now()->subDays(1)->toDateString(),
                'closed_status' => '0',
                'closing_status' => 'Chưa chốt đơn',
                'closing_status_label' => 'Chưa chốt đơn',
                'delivery_status' => null,
                'delivery_status_label' => null,
                'result_count' => 27,
            ],
            [
                'page_code' => '2.6.1',
                'page_title' => 'Báo cáo giao hàng',
                'filter_label' => 'Báo cáo giao hàng · Trạng thái giao hàng: Đã hoàn',
                'date_type' => 'NgayCapNhatTrangThaiGiaoHang',
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),
                'closed_status' => '1',
                'closing_status' => 'Đã chốt đơn',
                'closing_status_label' => 'Đã chốt đơn',
                'delivery_status' => '41',
                'delivery_status_label' => 'Đã hoàn',
                'result_count' => 4,
            ],
        ];

        foreach ($samples as $index => $sample) {
            $actor = $actors[$index % $actors->count()];
            $filters = array_filter([
                'date_type' => $sample['date_type'],
                'date_from' => $sample['date_from'],
                'date_to' => $sample['date_to'],
                'closed_status' => $sample['closed_status'],
                'delivery_status' => $sample['delivery_status'],
            ], fn ($value): bool => $value !== null && $value !== '');

            $log = ActivityLogger::log(
                ActivityLogger::DATA_FILTER_SEARCHED,
                null,
                array_merge($sample, [
                    'filters' => $filters,
                    'actor_name' => $actor->name,
                ]),
                $sample['filter_label'],
                $actor,
            );

            $log->forceFill(['created_at' => now()->subHours($index * 5 + 1)])->save();
        }

        $this->command?->info('Đã tạo lịch sử lọc data chốt đơn demo cho menu 1.7.3.');
    }
}
