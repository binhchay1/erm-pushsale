# Pushsale route & menu contract

## Mục tiêu

Menu, route và controller phải được thiết kế theo một nguồn chuẩn, không vá từng màn hình. Nguồn chuẩn ban đầu là HTML menu Pushsale gốc do khách gửi. File đối chiếu đã được xuất ra `docs/generated/pushsale_menu_route_catalog.csv` gồm toàn bộ cây menu, mã menu, title, href gốc và admin path tương ứng.

## Quy tắc route bắt buộc

1. Không đặt route theo kiểu `sale-1`, `sale-2`, `sale-4`, `kho-2` trong URL mới. Những key cũ chỉ được giữ ở tầng compatibility hoặc normalize, không được dùng trên menu.
2. URL mới phải nói rõ nghiệp vụ, ví dụ:
   - `/admin/sales/reports/sale-kpi`
   - `/admin/sales/reports/closing-summary`
   - `/admin/sales/reports/revenue-detail`
   - `/admin/marketing/reports/revenue-v2`
   - `/admin/warehouse/reports/revenue`
3. Link gốc Pushsale như `/ld/sale/bao-cao/bao-cao-doanh-so-v2` được giữ làm legacy alias/redirect hoặc map đến cùng controller. Không copy HTML mẫu thành route riêng.
4. Mỗi menu item phải có `code` đúng theo cây Pushsale. `activeMenuCode` dùng code, không đoán theo title.
5. Menu ngoài Pushsale, ví dụ giám sát hệ thống hoặc activity log, phải nằm trong nhóm riêng `10. Vận hành hệ thống`; không chen vào nhóm 4.5/4.6 chuẩn Pushsale.

## Quy tắc file/controller

1. Controller đặt theo business capability, không đặt theo phiên bản hoặc số prompt.
2. CSS/JS contract đặt theo mục đích, ví dụ `pushsale-report-toolbar-contract.css`, không đặt `v101`, `v102`.
3. Report dùng chung toolbar/filter contract. Không thêm CSS vá riêng từng trang nếu lỗi thuộc header/filter/bảng chung.
4. Route alias cũ chỉ để không chết link; menu và test mới phải dùng route canonical.

## Báo cáo extra report

Nguồn map report nằm ở `config/pushsale_report_routes.php`.

Các URL canonical hiện tại:

| Key | Canonical admin route |
| --- | --- |
| sale-kpi | /admin/sales/reports/sale-kpi |
| sale-closing-summary | /admin/sales/reports/closing-summary |
| sale-work | /admin/sales/reports/work |
| sale-revenue-detail | /admin/sales/reports/revenue-detail |
| sale-revenue | /admin/sales/reports/revenue |
| sale-revenue-v2 | /admin/sales/reports/revenue-v2 |
| sale-appointments | /admin/sales/reports/appointments |
| system-business | /admin/reports/system-business |
| marketing-1 | /admin/marketing/reports/revenue-detail |
| marketing-sales-summary | /admin/marketing/reports/revenue |
| marketing-sales-v2 | /admin/marketing/reports/revenue-v2 |
| marketing-3 | /admin/marketing/reports/work |
| marketing-4 | /admin/marketing/reports/upsale |
| warehouse-sales-summary | /admin/warehouse/reports/revenue |
| warehouse-sales-v2 | /admin/warehouse/reports/revenue-v2 |
| product-conversion | /admin/reports/product-conversion |

## Kiểm tra trước khi giao build

Chạy:

```bash
php -l routes/web.php
php -l app/Services/NavigationService.php
php -l app/Services/Reports/ExtraReportService.php
php -l app/Http/Controllers/Reports/ExtraReportController.php
php -l config/pushsale_report_routes.php
node ./scripts/audit-pushsale-routes.mjs
node ./scripts/audit-pushsale-contract.mjs
```

Trên server/dev đầy đủ dependency thì chạy thêm:

```bash
pnpm build
php artisan route:list | grep -E "sales/reports|marketing/reports|warehouse/reports|reports/system-business"
php artisan test
```
