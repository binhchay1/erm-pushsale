<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTaxonomyDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_demo_seed_contains_product_taxonomy_data_for_dialogs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(60, ProductCategory::query()->count(), 'Dialog phân loại sản phẩm cần đủ dữ liệu demo để kiểm tra bảng và phân trang.');
        $this->assertGreaterThanOrEqual(15, ProductAttribute::query()->count(), 'Dialog thuộc tính sản phẩm cần đủ dữ liệu demo để kiểm tra bảng và phân trang.');
        $this->assertGreaterThanOrEqual(150, ProductAttributeValue::query()->count(), 'Dialog giá trị thuộc tính cần đủ dữ liệu demo để kiểm tra bảng, select thuộc tính và phân trang.');

        $this->assertDatabaseHas('product_categories', ['name' => 'Thanh cua tẩm vị']);
        $this->assertDatabaseHas('product_attributes', ['name' => 'Màu sắc']);
        $this->assertDatabaseHas('product_attribute_values', ['name' => 'Hộp cam - Vị cay thơm']);

        $product = Product::query()->where('type', 'product')->with(['categories', 'attributeValues.attribute'])->first();

        $this->assertNotNull($product, 'Full seed phải có sản phẩm thật để liên kết taxonomy.');
        $this->assertGreaterThan(0, $product->categories->count(), 'Sản phẩm demo phải gắn phân loại để dialog liên kết đúng business.');
        $this->assertGreaterThan(0, $product->attributeValues->count(), 'Sản phẩm demo phải gắn giá trị thuộc tính để dialog liên kết đúng business.');
        $this->assertNotNull($product->attributeValues->first()?->attribute, 'Giá trị thuộc tính phải trỏ về thuộc tính cha.');
    }
}
