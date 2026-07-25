# v121 — Landing connection save 500 guard

Mục tiêu: sửa lỗi POST `/admin/marketing/landing-connections/records` rơi ra trang 500 khi tạo nguồn landing theo luồng mới.

## Thay đổi chính

- Form tạo/sửa nguồn landing không duyệt trực tiếp nữa. Dữ liệu được tạo ở trạng thái chờ duyệt.
- Sản phẩm/gói sản phẩm và ngân sách được gắn ở menu duyệt `/admin/marketing/landing-approvals`.
- Controller `LandingConnectionsController` đã bọc lỗi DB/runtime để không còn đẩy người dùng ra trang 500; lỗi được ghi log Laravel và trả về form bằng flash error.
- Migration + `erm:repair-schema-contract` bổ sung/repair các cột legacy của `marketing_sources` và `landing_connections`, đặc biệt cho phép `marketing_sources.product_id` nullable để lưu nguồn chờ duyệt.
- Vá CSS hover cuối cùng cho menu cấp 2 không có submenu cấp 3.

## Chạy sau khi deploy

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

## Test tay

1. Vào `/admin/marketing/landing-connections`.
2. Bấm `+ Thêm`.
3. Điền tên nguồn, URL landing, kênh quảng cáo, sale ưu tiên nếu cần.
4. Lưu: phải quay lại danh sách, không được rơi ra `/records` 500.
5. Vào `/admin/marketing/landing-approvals` để gắn sản phẩm/gói và ngân sách rồi duyệt.
