# V94 — Revenue detail marketing/sale report contract

## Phạm vi

- Làm lại trang `/admin/reports/extra/marketing-1` theo mẫu Pushsale `Báo cáo doanh số marketing`.
- Đồng bộ lại `sale-3` vì cùng loại báo cáo doanh số chi tiết 19 chỉ số.
- Giữ cấu trúc shell chung `PushsalePageShell`, không nhúng HTML DNN trực tiếp.

## Frontend

### `resources/js/pages/Reports/ExtraReport.jsx`

- Thêm field `marketing_team_leader_id` cho filter báo cáo.
- `RevenueDetailReport` không còn dùng `CommonToolbar`; chuyển sang toolbar riêng:
  - title + filter chính cùng hàng,
  - filter nâng cao bằng mũi tên,
  - actions bên phải: `Tìm kiếm`, `Xuất Excel`,
  - date range hiển thị dạng `dd/mm/yyyy 00:00 - dd/mm/yyyy 23:59` giống Pushsale.
- Thêm phần chú giải công thức (1)–(19) như Pushsale và thêm note `Upsale`.
- Bảng `tableReport` giữ cấu trúc 2 tầng:
  - 9 nhóm trạng thái: Chốt đơn, XNGH, Huỷ chốt, Chuyển ĐVGH, Đã hoàn, Đang hoàn, Đã giao, Đã thanh toán, Giao thành công.
  - các cột tỷ lệ 10–19.
  - nhóm `UPSALE` ở cuối để phù hợp business mới.

### `resources/css/pushsale-revenue-detail-report-contract.css`

- Scope riêng `.ps-revenue-detail-page` để không ảnh hưởng page khác.
- Ép toolbar/header/filter/table theo Pushsale.
- Ép menu sidebar scroll trong vùng sidebar khi dropdown dài quá viewport.

### `resources/js/lib/pushsaleStyleRegistry.js`

- Đăng ký CSS V94 vào final-contract layer.

## Backend

### `app/Services/Reports/ExtraReportService.php`

- Mở rộng filter của `marketing-1` và `sale-3`:
  - date_type, date_from/date_to,
  - discount_mode,
  - reconciliation_status,
  - team/marketing team leader + team,
  - parent_product_id/product_id,
  - delivery_status,
  - per_page,
  - no_closing_date_limit.
- `revenueDetail()` dùng `reportRevenue()` để tôn trọng filter trước/sau chiết khấu.
- Upsale nhận diện bằng `isUpsellItem()` thay vì chỉ check `item_type = upsell`.
- Tách doanh số item theo `itemRevenue()` để `before_discount` tính `unit_price * quantity`, `after_discount` tính `lineTotal()`.

### `app/Models/Order.php`

- `parent_product_id` filter giờ kiểm tra cả product chính của đơn và product trong `order_items`.

## Menu

### `config/pushsale_navigation.php`

- Menu `2.7 Báo cáo` đưa về đúng thứ tự mẫu Pushsale:
  1. Báo cáo doanh số marketing
  2. Báo cáo doanh số
  3. Báo cáo doanh số V2
  4. CEO Dashboard V2
  5. Báo cáo công việc
  6. Báo cáo kinh doanh hệ thống
- Các báo cáo mới như upsale/product conversion vẫn nằm ở các nhánh chuyên biệt khác, không chèn thêm vào 2.7 làm lệch menu mẫu.

## Test/Audit đã chạy trong sandbox

```bash
php -l app/Models/Order.php
php -l app/Services/Reports/ExtraReportService.php
php -l config/pushsale_navigation.php
php -l tests/Feature/Reports/TemplateSixReportsTest.php
node --check resources/js/lib/pushsaleStyleRegistry.js
node ./scripts/audit-pushsale-contract.mjs
```

Kết quả audit: `33 pass, 16 warn, 0 fail`.

Chưa chạy được `pnpm build` và `php artisan test` đầy đủ trong sandbox vì không có `node_modules/` và `vendor/`.
