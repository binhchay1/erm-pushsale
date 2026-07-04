<?php

namespace App\Console\Commands;

use App\Models\MarketingSource;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Rà soát & sửa dữ liệu cũ không còn khớp với business logic mới.
 *
 * Mặc định chạy ở chế độ báo cáo (dry-run). Thêm --fix để thực sự sửa dữ liệu.
 *
 * Các quy tắc hiện có:
 *  1. Chiến dịch gốc (parent_id = null) thiếu product_id mà vẫn is_active/is_approved
 *     → tắt nhận lead (is_active=false, is_approved=false) để không tạo đơn rỗng (0đ, không sản phẩm).
 *     Admin sẽ gán sản phẩm lại qua màn Duyệt landing.
 */
class AuditBusinessDataCommand extends Command
{
    protected $signature = 'data:audit-business {--fix : Thực sự sửa dữ liệu thay vì chỉ báo cáo}';

    protected $description = 'Rà soát & sửa dữ liệu cũ không khớp business logic mới (vd chiến dịch thiếu product_id)';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $this->info($fix ? '== CHẾ ĐỘ SỬA DỮ LIỆU (--fix) ==' : '== CHẾ ĐỘ BÁO CÁO (dry-run) — thêm --fix để sửa ==');

        $this->auditCampaignsMissingProduct($fix);
        $this->reportOrdersWithoutProduct();

        $this->newLine();
        $this->info('Hoàn tất.');

        return self::SUCCESS;
    }

    private function auditCampaignsMissingProduct(bool $fix): void
    {
        $query = MarketingSource::query()
            ->whereNull('parent_id')
            ->whereNull('product_id')
            ->where(function ($q) {
                $q->where('is_active', true)->orWhere('is_approved', true);
            });

        $count = (clone $query)->count();

        $this->newLine();
        $this->line("1) Chiến dịch gốc thiếu product_id nhưng đang bật/đã duyệt: <comment>{$count}</comment>");

        if ($count === 0) {
            return;
        }

        (clone $query)->select(['id', 'name', 'is_active', 'is_approved'])
            ->orderBy('id')
            ->get()
            ->each(fn ($c) => $this->line("   - #{$c->id} {$c->name} (active={$c->is_active}, approved={$c->is_approved})"));

        if (! $fix) {
            $this->warn('   → Sẽ tắt nhận lead cho các chiến dịch trên khi chạy với --fix.');

            return;
        }

        $updated = (clone $query)->update([
            'is_active' => false,
            'is_approved' => false,
        ]);

        $this->info("   → Đã tắt nhận lead cho {$updated} chiến dịch. Admin gán sản phẩm lại ở màn Duyệt landing.");
    }

    private function reportOrdersWithoutProduct(): void
    {
        $emptyLanding = Order::query()
            ->whereNull('product_id')
            ->whereDoesntHave('items')
            ->count();

        $this->newLine();
        $this->line("2) Đơn không có product_id và không có dòng sản phẩm: <comment>{$emptyLanding}</comment>");

        if ($emptyLanding > 0) {
            $this->warn('   → Đây là đơn rỗng sinh ra từ chiến dịch thiếu sản phẩm. Cần rà soát thủ công (gán sản phẩm hoặc hủy đơn).');
        }
    }
}
