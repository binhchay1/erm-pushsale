<?php

namespace Database\Seeders;

use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Leads\ManualLeadImportService;
use Illuminate\Database\Seeder;

/**
 * Demo data cho menu 2.6.2 - Nhập data thủ công.
 *
 * Dữ liệu được đẩy qua ManualLeadImportService để vẫn đi đúng luồng nhận lead:
 * marketing source -> sản phẩm quan tâm -> chia sale/order/report.
 */
class ManualMarketingContactSeeder extends Seeder
{
    public function __construct(
        private readonly ManualLeadImportService $manualLeads,
    ) {}

    public function run(): void
    {
        $sources = MarketingSource::query()->where('is_active', true)->orderBy('id')->limit(40)->get();
        $products = Product::query()->where('is_active', true)->orderBy('id')->limit(40)->get();
        $sales = User::query()->where('role', User::ROLE_SALES)->orderBy('id')->get()->values();

        if ($sources->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('Bỏ qua ManualMarketingContactSeeder: thiếu nguồn dữ liệu hoặc sản phẩm.');
            return;
        }

        $names = [
            'Nguyễn Minh An', 'Trần Khánh Ly', 'Lê Hoài Thu', 'Phạm Gia Hân', 'Đặng Nhật Minh',
            'Hoàng Bảo Châu', 'Vũ Ngọc Anh', 'Bùi Thành Nam', 'Đỗ Lan Phương', 'Mai Thảo Vy',
            'Tạ Đức Anh', 'Cao Mỹ Linh', 'Lý Tuấn Kiệt', 'Hà Phương Nhi', 'Phan Quốc Huy',
            'Dương Hải Yến', 'Trịnh Bảo Ngọc', 'Ngô Gia Bảo', 'Đinh Thanh Tâm', 'Chu Quang Vinh',
        ];
        $messages = [
            'Khách hỏi giá và muốn tư vấn size phù hợp.',
            'Khách cần gọi lại sau giờ hành chính.',
            'Quan tâm combo, xin ưu đãi vận chuyển.',
            'Đã inbox fanpage, cần telesale xác nhận đơn.',
            'Khách cũ muốn mua thêm cho người thân.',
            'Cần kiểm tra tồn kho trước khi báo khách.',
            'Khách muốn giao nhanh trong ngày.',
            'Lead nhập tay từ hotline, chưa có địa chỉ chi tiết.',
        ];

        for ($i = 0; $i < 120; $i++) {
            $source = $sources[$i % $sources->count()];
            $primary = $products[$i % $products->count()];
            $secondary = $products[($i + 7) % $products->count()];
            $sale = $sales->isNotEmpty() ? $sales[$i % $sales->count()] : null;
            $phone = '09'.str_pad((string) (32000000 + $i), 8, '0', STR_PAD_LEFT);

            $lead = $this->manualLeads->createSingle([
                'marketing_source_id' => $source->id,
                'product_ids' => $i % 5 === 0 ? [$primary->id, $secondary->id] : [$primary->id],
                'name' => $names[$i % count($names)],
                'phone' => $phone,
                'message' => $messages[$i % count($messages)],
                'address' => (($i % 2) ? 'Hà Nội' : 'TP.HCM').' - demo nhập tay '.($i + 1),
                'utm_source' => $source->utm_source ?: 'manual',
                'utm_campaign' => $source->utm_campaign ?: 'manual-contact-demo',
            ], $sale);

            if ($lead instanceof LeadIngestion) {
                $lead->forceFill([
                    'created_at' => now()->subMinutes(120 - $i),
                    'updated_at' => now()->subMinutes(120 - $i),
                ])->save();
            }
        }

        $this->command?->info('Đã tạo 120 contact nhập tay qua đúng luồng business.');
    }
}
