<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Rà soát dữ liệu nghiệp vụ — báo cáo các trường hợp cần xem lại thủ công.
 */
class AuditBusinessDataCommand extends Command
{
    protected $signature = 'data:audit-business {--fix : Giữ tương thích deploy script (hiện chỉ báo cáo)}';

    protected $description = 'Rà soát dữ liệu nghiệp vụ (đơn rỗng, v.v.)';

    public function handle(): int
    {
        $this->info('== RÀ SOÁT DỮ LIỆU NGHIỆP VỤ ==');

        $this->reportOrdersWithoutItems();

        $this->newLine();
        $this->info('Hoàn tất.');

        return self::SUCCESS;
    }

    private function reportOrdersWithoutItems(): void
    {
        $emptyOrders = Order::query()
            ->whereDoesntHave('items')
            ->count();

        $this->newLine();
        $this->line("Đơn không có dòng sản phẩm: <comment>{$emptyOrders}</comment>");

        if ($emptyOrders > 0) {
            $this->warn('   → Thường là lead chỉ có tên/SĐT, chưa có combo/sản phẩm từ landing. Sale cần bổ sung khi tác nghiệp.');
        }
    }
}
