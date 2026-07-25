# v122 - Landing connection backend reset

## Mục tiêu

Luồng cũ tạo `marketing_sources` ngay khi Marketing tạo kết nối landing. Điều này sai với business mới vì thời điểm tạo landing chưa có sản phẩm/gói sản phẩm và ngân sách. Trên staging nó gây lỗi khi `marketing_sources.product_id` hoặc các cột legacy chưa khớp schema.

v122 tách rõ 2 bước:

1. **Menu 2.4.1 - Kết nối landing / nguồn dữ liệu**
   - Chỉ tạo `landing_connections`, `landing_connection_sources`, `landing_connection_sales`.
   - Không tạo campaign legacy `marketing_sources` khi chưa duyệt.
   - Không yêu cầu sản phẩm/gói sản phẩm.
   - URL API là trường read-only, tự sinh sau khi lưu.

2. **Menu 2.4.3 - Duyệt kết nối dữ liệu**
   - Người duyệt chọn sản phẩm/gói sản phẩm.
   - Nhập ngân sách tổng hoặc ngân sách/ngày nếu cần.
   - Khi duyệt mới đồng bộ sang `marketing_sources` để các luồng báo cáo/lead cũ vẫn dùng được.

## Các file chính đã sửa

- `app/Services/Marketing/LandingConnectionManager.php`
- `app/Http/Controllers/Admin/Marketing/LandingConnectionsController.php`
- `app/Http/Controllers/Admin/LandingApprovalController.php`
- `routes/web.php`
- `routes/pushsale_pages.php`
- `resources/js/pages/Pushsale/Pages/Marketing/LandingConnectionsPage.jsx`
- `resources/css/pushsale-landing-connections.css`
- `app/Support/RuntimeSchemaContract.php`
- `database/migrations/2026_07_25_122000_repair_landing_pending_source_contract.php`

## Test sau deploy

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

Test browser:

- `/admin/marketing/landing-connections`
- tạo mới landing, chỉ điền tên + URL nguồn dữ liệu + kênh quảng cáo + sale nếu cần
- `/admin/marketing/landing-approvals`
- duyệt landing, chọn sản phẩm/gói và ngân sách

## Smoke test v122

Route smoke chỉ fail command khi có lỗi nghiêm trọng: exception/PHP error/HTTP 5xx. Các 403/404 được coi là warning để không làm nhiễu khi mục tiêu là quét lỗi 500 của route view.
