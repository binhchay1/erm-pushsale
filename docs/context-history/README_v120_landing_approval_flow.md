# v120 - Landing connection approval flow + CSS contract fix

## Nội dung thay đổi

- Menu 2.4.1 `Kết nối landing` chỉ tạo kết nối nguồn dữ liệu, không bắt buộc chọn sản phẩm ở dialog thêm/sửa.
- Menu 2.4.3 `Duyệt kết nối dữ liệu` là bước duyệt riêng cho Admin/Marketing leader:
  - Chọn sản phẩm/gói sản phẩm thật từ catalog.
  - Nhập ngân sách tổng hoặc ngân sách/ngày.
  - Duyệt kết nối để bật luồng nhận data thật.
- Backend route `/admin/marketing/landing-connections/records` không còn validate bắt buộc `products` ở bước tạo kết nối.
- Seeder `LandingConnectionDemoSeeder` tạo cả bản ghi đã duyệt và bản ghi chờ duyệt chưa gắn sản phẩm để test đúng luồng mới.
- Fix CSS hover menu cấp 2 không có submenu cấp 3: hover phải nền xanh, chữ trắng, không còn border xanh mờ cạnh trên.

## Lệnh chạy sau khi copy source

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan db:seed
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

## URL cần test

```text
/admin/marketing/landing-connections
/admin/marketing/landing-approvals
/ld/unit-admin/ket-noi-landing-website?tid=2
```

## Lưu ý asset

Không xóa các thư mục asset legacy này khi copy source vào git/server:

```text
public/vendor/adminlte2
public/vendor/font-awesome
public/vendor/bootstrap
```

Các icon action và giao diện Pushsale cũ vẫn đang phụ thuộc FontAwesome/AdminLTE legacy.
