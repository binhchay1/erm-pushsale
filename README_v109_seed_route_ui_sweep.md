# v109 — Seed, route 500, page spacing, menu hover sweep

Bản này sửa các lỗi phát hiện trực tiếp trên staging sau v108:

## Backend/seed
- `SalesPipelineSeeder` không còn chết khi một campaign/source không map được `product_id`.
- Nếu campaign thiếu sản phẩm, seeder tự lấy sản phẩm đang kinh doanh đầu tiên và gắn lại vào campaign trước khi tạo order demo.
- Giá dòng order/upsell được ép an toàn để không đọc property trên null.

## Route/view 500
- `PushsalePageService` dùng đúng cột `teams.leader_user_id` thay vì `leader_id`.
- `BasePushsalePageController` bổ sung `activeMenuCodeFromRequest()` để các page controller kế thừa không 500, gồm `/admin/catalog/combos`.
- Route smoke nội bộ tạm bật `app.debug=true` trong đúng request smoke để `failed_top` in ra exception/SQL thật thay vì trang lỗi chung “Liên hệ …”.

## UI
- Gỡ double top offset của `.content-wrapper`, bỏ khoảng trắng đầu trang dưới header xanh trên toàn bộ admin pages.
- Chuẩn hóa filter trang quản lý sản phẩm: title/search gọn, filter sort/status/category/marketing ở hàng 1, sale/care ở hàng 2.
- Menu con cấp 2 không có submenu cấp 3, ví dụ 2.1/2.2/2.3, hover/active nền xanh giống các dòng có flyout.

## Lệnh test đề xuất

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:test-all --seed --audit --route-smoke --smoke-limit=50 --base-url=https://salesloop.vn --json
```

Chỉ test route/view:

```bash
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```
