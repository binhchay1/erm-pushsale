<?php

namespace App\Console\Commands;

use App\Services\Demo\WorkspaceUiDemoService;
use Illuminate\Console\Command;

/**
 * Seed / xóa dữ liệu demo UI cho sale, thủ kho, hồ sơ khách hàng.
 *
 * Chỉ tạo/xóa bản ghi gắn nhãn UXDEMO — không đụng dữ liệu khác.
 *
 * Ví dụ:
 *   php artisan demo:workspace-ui
 *   php artisan demo:workspace-ui seed
 *   php artisan demo:workspace-ui delete
 *   php artisan demo:workspace-ui delete --force
 */
class WorkspaceUiDemoCommand extends Command
{
    protected $signature = 'demo:workspace-ui
                            {action=seed : seed|delete (alias: purge|clear|reset)}
                            {--company= : Company id (mặc định company nội bộ)}
                            {--force : Bỏ qua xác nhận khi delete}';

    protected $description = 'Seed/xóa demo UI sale + thủ kho + hồ sơ khách hàng (chỉ bản ghi UXDEMO)';

    public function handle(WorkspaceUiDemoService $demo): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        $companyId = $this->option('company') !== null && $this->option('company') !== ''
            ? (int) $this->option('company')
            : null;

        if (in_array($action, ['delete', 'purge', 'clear', 'reset', 'remove'], true)) {
            if (! $this->option('force') && ! $this->confirm('Xóa toàn bộ bản ghi UXDEMO (sale/kho/hồ sơ KH)? Dữ liệu khác không bị ảnh hưởng.', false)) {
                $this->warn('Đã hủy.');

                return self::SUCCESS;
            }

            $counts = $demo->purge($companyId);
            $this->info('Đã xóa demo UXDEMO.');
            $this->table(array_keys($counts), [array_values($counts)]);

            return self::SUCCESS;
        }

        if (! in_array($action, ['seed', 'add', 'create', 'up'], true)) {
            $this->error("Action không hợp lệ: {$action}. Dùng seed hoặc delete.");

            return self::FAILURE;
        }

        try {
            $counts = $demo->seed($companyId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Đã tạo demo UXDEMO cho sale / thủ kho / hồ sơ KH / báo cáo Leader.');
        $this->table(array_keys($counts), [array_values($counts)]);
        $this->line('Gợi ý UI:');
        $this->line('  - Sale workspace / tác nghiệp: đơn UXDEMO-0001…');
        $this->line('  - Hồ sơ KH: SĐT 0988700001… + tin nhắn [UXDEMO]');
        $this->line('  - Thủ kho: phiếu UXDEMO-PN-* , biên bản UXDEMO BB *');
        $this->line('  - Báo cáo 4.6.x / doanh số: đơn UXDEMO theo nhiều sale + stage + kỳ ngày');
        $this->line('Xóa: php artisan demo:workspace-ui delete --force');

        return self::SUCCESS;
    }
}
