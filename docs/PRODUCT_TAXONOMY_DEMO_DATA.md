# Product taxonomy demo data

Seeder chính: `Database\\Seeders\\ProductTaxonomyDemoSeeder`.

Mục đích:
- Có dữ liệu thật để kiểm tra 3 dialog trong trang danh sách sản phẩm:
  - Phân loại sản phẩm
  - Thuộc tính sản phẩm
  - Giá trị thuộc tính
- Có đủ số dòng để kiểm tra search, filter theo thuộc tính, edit/delete và pagination.
- Có liên kết business với `products` qua:
  - `product_category_product`
  - `product_attribute_value_product`

## Dữ liệu được tạo

- Tối thiểu 67 phân loại sản phẩm.
- Tối thiểu 18 thuộc tính sản phẩm.
- Tối thiểu 180 giá trị thuộc tính.
- Các sản phẩm demo được gắn phân loại và giá trị thuộc tính thật để UI không rỗng.

## Lệnh dùng khi muốn xem UI trong browser

```bash
php artisan migrate:fresh --seed
```

Hoặc khi DB đã có sẵn:

```bash
php artisan db:seed --class=ProductTaxonomyDemoSeeder
```

## Lệnh test

```bash
php artisan test --filter=ProductTaxonomyDemoSeederTest
```

`php artisan test` dùng database testing theo `phpunit.xml`, vì vậy dữ liệu trong test được tạo để validate fixture và giao diện/backend contract. Muốn xem bằng browser trên DB staging/local thì dùng seed command ở trên.
