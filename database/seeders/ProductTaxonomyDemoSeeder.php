<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dữ liệu demo cho 3 popup sản phẩm Pushsale:
 * - Phân loại sản phẩm
 * - Thuộc tính sản phẩm
 * - Giá trị thuộc tính
 *
 * Seeder này được gọi trong DatabaseSeeder để khi chạy migrate:fresh --seed
 * có thể mở UI kiểm tra ngay. Các test cũng gọi DatabaseSeeder nên
 * `php artisan test` luôn có fixture để validate dialog taxonomy.
 */
class ProductTaxonomyDemoSeeder extends Seeder
{
    /** @var list<string> */
    private array $categoryNames = [
        'Thanh cua tẩm vị', 'Trâm cài tóc chữ U đính ngọc', 'Chì kẻ mày 2 đầu Luminee NK', 'Bật lửa điện tử Plasma mini',
        'Dầu gội phủ bạc NEZHA', 'Âu inox đựng dầu mỡ', 'Bộ trang sức hoa pha lê NK', 'Son môi thạch hoa NK',
        'Thanh năng lượng', 'Thanh protein bar mini', 'Bút nhũ', 'Khăn choàng ren cổ điển', 'Tinh dầu dưỡng tóc Raip NK',
        'Dầu gội nhuộm tóc Bunchar dạng gói', 'Set 6 đôi khuyên tai mạ bạc NK', 'Serum Vitamin C', 'Camera an ninh mini',
        'Gối mây đan cao cấp', 'Bột diệt cỏ sinh học', 'Kem chống nắng nâng tone', 'Sữa rửa mặt dịu nhẹ',
        'Nước hoa mini bỏ túi', 'Túi đựng mỹ phẩm du lịch', 'Hộp quà chăm sóc da', 'Combo chăm sóc tóc',
        'Bộ dụng cụ nhà bếp', 'Máy massage cổ vai gáy', 'Đèn led trang điểm', 'Sạc nhanh đa cổng', 'Tai nghe bluetooth mini',
        'Bình giữ nhiệt inox', 'Khăn lau đa năng', 'Kệ gia vị nhà bếp', 'Túi hút chân không', 'Áo chống nắng nữ',
        'Dép đi trong nhà', 'Găng tay chống nắng', 'Bàn chải điện mini', 'Máy tỉa lông mày', 'Lược massage da đầu',
        'Dầu xả phục hồi tóc', 'Mặt nạ ngủ cấp ẩm', 'Son dưỡng môi organic', 'Phấn phủ kiềm dầu', 'Kem nền mỏng nhẹ',
        'Tẩy tế bào chết', 'Xịt khoáng cấp ẩm', 'Nước tẩy trang dịu nhẹ', 'Bông tẩy trang cotton', 'Mút trang điểm',
        'Cọ trang điểm cá nhân', 'Nước giặt xả 2 trong 1', 'Viên giặt tiện lợi', 'Nước lau sàn thảo mộc', 'Bột thông cống sinh học',
        'Hộp cơm văn phòng', 'Bộ thìa dĩa inox', 'Máy ép chậm mini', 'Máy xay sinh tố cầm tay', 'Khay đá silicon',
        'Đồ chơi giáo dục trẻ em', 'Đèn ngủ cảm ứng', 'Thảm chống trượt nhà tắm', 'Móc treo quần áo đa năng',
        'Túi đựng chăn màn', 'Bộ lau kính thông minh', 'Bình xịt tưới cây mini',
    ];

    /** @var list<string> */
    private array $attributeNames = [
        'Màu sắc', 'Dung tích', 'Kích thước', 'Hương vị', 'Chất liệu', 'Quy cách đóng gói', 'Mùi hương', 'Loại da',
        'Trọng lượng', 'Phiên bản', 'Công suất', 'Size', 'Kiểu dáng', 'Độ tuổi sử dụng', 'Mùa sử dụng', 'Nguồn gốc',
        'Bộ sản phẩm', 'Dòng sản phẩm',
    ];

    /** @var array<string,list<string>> */
    private array $valueMap = [
        'Màu sắc' => ['Màu 01: Đen', 'Màu 02: Nâu sẫm', 'Màu 03: Nâu vừa', 'Màu 04: Nâu sáng', 'Màu xanh', 'Màu đen', 'Màu nâu hạt dẻ', 'Ngọc Trắng', 'Ngọc Xanh', 'Ngọc Nâu'],
        'Dung tích' => ['30 ml', '50 ml', '100 ml', '150 ml', '250 ml', '300 ml', '500 ml', '1 Lít', '3 Lít', '5 Lít'],
        'Kích thước' => ['Size mini', 'Size nhỏ', 'Size vừa', 'Size lớn', 'Size đại', 'Dài 10cm', 'Dài 20cm', 'Dài 30cm', 'Bộ 3 size', 'Bộ 5 size'],
        'Hương vị' => ['Hộp cam - Vị cay thơm', 'Hộp đỏ - Vị BBQ', 'Hộp xanh - Nguyên vị', 'Vị rong biển', 'Vị phô mai', 'Vị bò nướng', 'Vị gà quay', 'Vị mật ong', 'Vị trà xanh', 'Vị cà phê'],
        'Chất liệu' => ['Inox 304', 'Nhựa ABS', 'Silicon mềm', 'Cotton', 'Polyester', 'Da PU', 'Thủy tinh', 'Gỗ tre', 'Hợp kim', 'Vải không dệt'],
        'Quy cách đóng gói' => ['Gói lẻ', 'Hộp 1 sản phẩm', 'Hộp 2 sản phẩm', 'Combo 3 sản phẩm', 'Combo 5 sản phẩm', 'Túi zip', 'Vỉ treo', 'Thùng 12 hộp', 'Set quà tặng', 'Bản dùng thử'],
        'Mùi hương' => ['Không mùi', 'Hương hoa hồng', 'Hương trà xanh', 'Hương lavender', 'Hương cam chanh', 'Hương bạc hà', 'Hương sữa', 'Hương gỗ', 'Hương biển', 'Hương vanilla'],
        'Loại da' => ['Da thường', 'Da khô', 'Da dầu', 'Da hỗn hợp', 'Da nhạy cảm', 'Da mụn', 'Da nám', 'Da lão hóa', 'Da thiếu ẩm', 'Mọi loại da'],
        'Trọng lượng' => ['50g', '100g', '150g', '250g', '300g', '500g', '750g', '1kg', '1.5kg', '2kg'],
        'Phiên bản' => ['Bản tiêu chuẩn', 'Bản cao cấp', 'Bản tiết kiệm', 'Bản giới hạn', 'Bản mới 2026', 'Bản không hộp', 'Bản có hộp', 'Bản combo', 'Bản refill', 'Bản mini'],
        'Công suất' => ['5W', '10W', '15W', '20W', '30W', '45W', '60W', '100W', '120W', 'Không dùng điện'],
        'Size' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free size', 'Size 35-36', 'Size 37-38', 'Size 39-40'],
        'Kiểu dáng' => ['Cổ điển', 'Hiện đại', 'Tối giản', 'Nắp bật', 'Nắp xoay', 'Dạng tuýp', 'Dạng chai', 'Dạng hộp', 'Dạng túi', 'Dạng thanh'],
        'Độ tuổi sử dụng' => ['Trẻ em', 'Từ 6 tuổi', 'Từ 12 tuổi', 'Người lớn', 'Người cao tuổi', 'Mẹ và bé', 'Nam giới', 'Nữ giới', 'Gia đình', 'Dân văn phòng'],
        'Mùa sử dụng' => ['Mùa hè', 'Mùa đông', 'Mùa mưa', 'Mùa khô', 'Quanh năm', 'Du lịch', 'Tết', 'Back to school', 'Lễ hội', 'Hằng ngày'],
        'Nguồn gốc' => ['Nội địa', 'Nhập khẩu Hàn Quốc', 'Nhập khẩu Nhật Bản', 'Nhập khẩu Thái Lan', 'Nhập khẩu Trung Quốc', 'OEM Việt Nam', 'Hàng công ty', 'Hàng phân phối', 'Hàng xách tay', 'Hàng độc quyền'],
        'Bộ sản phẩm' => ['Bộ 1 món', 'Bộ 2 món', 'Bộ 3 món', 'Bộ 4 món', 'Bộ 5 món', 'Bộ 7 món', 'Bộ 10 món', 'Bộ tiết kiệm', 'Bộ gia đình', 'Bộ dùng thử'],
        'Dòng sản phẩm' => ['Basic', 'Premium', 'Professional', 'Organic', 'Sensitive', 'Repair', 'Daily Care', 'Travel Size', 'Family Pack', 'Limited'],
    ];

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@saleops.local')->first()
            ?: User::query()->where('email', 'superadmin@saleops.local')->first();
        $userId = $admin?->id;

        $categories = collect($this->categoryNames)
            ->map(fn (string $name, int $index) => $this->upsertCategory($name, $index + 1, $userId));

        $attributes = collect($this->attributeNames)
            ->map(fn (string $name) => $this->upsertAttribute($name, $userId));

        $valuesByAttribute = $attributes->mapWithKeys(function (ProductAttribute $attribute) use ($userId): array {
            $values = $this->valueMap[$attribute->name] ?? [];

            $created = collect($values)->map(fn (string $name, int $index) => $this->upsertAttributeValue($attribute, $name, $index + 1, $userId));

            return [$attribute->name => $created];
        });

        $this->attachDemoTaxonomyToProducts($categories, $valuesByAttribute);

        $this->command?->info(sprintf(
            'Đã tạo dữ liệu demo taxonomy: %d phân loại, %d thuộc tính, %d giá trị thuộc tính.',
            ProductCategory::query()->count(),
            ProductAttribute::query()->count(),
            ProductAttributeValue::query()->count(),
        ));
    }

    private function upsertCategory(string $name, int $sortOrder, ?int $userId): ProductCategory
    {
        /** @var ProductCategory $category */
        $category = ProductCategory::withTrashed()->firstOrNew(['name' => $name]);
        $category->fill([
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_by_user_id' => $category->exists ? $category->created_by_user_id : $userId,
            'updated_by_user_id' => $userId,
        ]);
        if ($category->exists && method_exists($category, 'trashed') && $category->trashed()) {
            $category->restoreQuietly();
        }
        $category->save();

        return $category;
    }

    private function upsertAttribute(string $name, ?int $userId): ProductAttribute
    {
        /** @var ProductAttribute $attribute */
        $attribute = ProductAttribute::withTrashed()->firstOrNew(['name' => $name]);
        $attribute->fill([
            'is_active' => true,
            'created_by_user_id' => $attribute->exists ? $attribute->created_by_user_id : $userId,
            'updated_by_user_id' => $userId,
        ]);
        if ($attribute->exists && method_exists($attribute, 'trashed') && $attribute->trashed()) {
            $attribute->restoreQuietly();
        }
        $attribute->save();

        return $attribute;
    }

    private function upsertAttributeValue(ProductAttribute $attribute, string $name, int $sortOrder, ?int $userId): ProductAttributeValue
    {
        /** @var ProductAttributeValue $value */
        $value = ProductAttributeValue::withTrashed()->firstOrNew([
            'product_attribute_id' => $attribute->id,
            'name' => $name,
        ]);
        $value->fill([
            'sort_order' => $sortOrder,
            'created_by_user_id' => $value->exists ? $value->created_by_user_id : $userId,
            'updated_by_user_id' => $userId,
        ]);
        if ($value->exists && method_exists($value, 'trashed') && $value->trashed()) {
            $value->restoreQuietly();
        }
        $value->save();

        return $value;
    }

    /**
     * @param Collection<int,ProductCategory> $categories
     * @param Collection<string,Collection<int,ProductAttributeValue>> $valuesByAttribute
     */
    private function attachDemoTaxonomyToProducts(Collection $categories, Collection $valuesByAttribute): void
    {
        $categoryIds = $categories->pluck('id')->values();
        $colorValues = $valuesByAttribute->get('Màu sắc', collect())->pluck('id')->values();
        $sizeValues = $valuesByAttribute->get('Kích thước', collect())->pluck('id')->values();
        $packValues = $valuesByAttribute->get('Quy cách đóng gói', collect())->pluck('id')->values();

        Product::query()->orderBy('id')->get()->values()->each(function (Product $product, int $index) use ($categoryIds, $colorValues, $sizeValues, $packValues): void {
            if ($categoryIds->isNotEmpty()) {
                $product->categories()->syncWithoutDetaching([
                    $categoryIds[$index % $categoryIds->count()],
                ]);
            }

            $attributeValueIds = collect([$colorValues, $sizeValues, $packValues])
                ->filter(fn (Collection $ids): bool => $ids->isNotEmpty())
                ->map(fn (Collection $ids): int => (int) $ids[$index % $ids->count()])
                ->unique()
                ->values()
                ->all();

            if ($attributeValueIds !== []) {
                $product->attributeValues()->syncWithoutDetaching($attributeValueIds);
            }
        });
    }
}
