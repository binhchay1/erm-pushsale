# Pushsale report contract map

Tài liệu này dùng để tránh tình trạng một menu báo cáo trỏ sang template cũ hoặc trỏ nhầm sang báo cáo khác. Quy ước mới: **không đặt file/controller theo số menu** cho luồng mới, ví dụ không tạo `Page_8_1_1`, `Page1_4_2`, `page-8-1-1.blade.php`. Số menu chỉ nằm ở navigation/config/test metadata. File code phải đặt theo nghiệp vụ để những menu cùng mục đích dùng chung được.

## Quy ước đặt tên

| Loại | Quy ước |
| --- | --- |
| Controller report mới | `app/Http/Controllers/Reports/<BusinessName>Controller.php` |
| Service tính số liệu | `app/Services/Reports/<BusinessName>Service.php` |
| Inertia page | `resources/js/pages/Reports/<BusinessName>.jsx` |
| CSS parity Pushsale | `resources/css/pushsale-<business-name>-contract.css` |
| Seeder demo | `database/seeders/<BusinessName>Seeder.php` |
| Legacy Page* | Chỉ giữ trong `app/Http/Controllers/Admin/Pushsale/Pages` cho trang migrate tạm. Khi rebuild thật phải chuyển sang tên nghiệp vụ. |

## Menu 8.1.1 — Biểu đồ thống kê theo khung giờ

| Hạng mục | File/route dùng chung |
| --- | --- |
| Legacy route Pushsale | `/ld/thong-ke` |
| Admin route | `/admin/reports/hourly` |
| Marketing route | `/marketing/reports/hourly` |
| Sales route | `/sales/reports/hourly` |
| Controller | `app/Http/Controllers/Reports/HourlyStatsController.php` |
| Service | `app/Services/Reports/HourlyStatsService.php` |
| Page | `resources/js/pages/Reports/HourlyStats.jsx` |
| CSS | `resources/css/pushsale-hourly-statistics-contract.css` |
| Seeder | `database/seeders/HourlyStatsSeeder.php` |
| Navigation code | `8.1.1` |

Ghi chú nghiệp vụ: báo cáo lấy contact theo `orders.data_arrived_at`, đơn chốt/doanh số theo `orders.closed_at` và `orders.closing_status = closed`. Không dùng HTML tĩnh. Dữ liệu demo phải đi qua bảng `orders`, `order_items`, `users`, `products`, `marketing_sources` để các báo cáo khác vẫn đối chiếu được.

## Menu 8.1.3 / 2.8.3 — Báo cáo up sale

| Hạng mục | File/route dùng chung |
| --- | --- |
| Legacy routes | `/ld/thong-ke/bao-cao-up-sale?menu=8.1.3`, `/ld/thong-ke/bao-cao-up-sale?menu=2.8.3` |
| Controller | `app/Http/Controllers/Reports/ExtraReportController.php` với key `marketing-4` |
| Service | `app/Services/Reports/ExtraReportService.php` |
| Page section | `resources/js/pages/Reports/ExtraReport.jsx` — component `MarketingUpsaleReport` |
| CSS | `resources/css/pushsale-marketing-upsale-contract.css` |

Ghi chú UI: không set height cố định cho vùng bảng. Vùng scroll ngang phải bám ngay dưới bảng; nếu ít dòng thì không được sinh khoảng trắng rỗng như màn hình cũ.

## Menu link trùng mục đích đã chuẩn hóa

| Menu | Trước đây | Sau khi chuẩn hóa |
| --- | --- | --- |
| 8.3.2 Bảng tổng hợp chờ xuất theo ngày | Trỏ nhầm CEO/accounting dashboard | `/admin/warehouse/reports/pending-export` |
| 8.3.3 Báo cáo giá vốn sản phẩm | Trỏ nhầm `/admin/reports/hourly` | `/admin/warehouse/reports/movement-summary` cho nhóm báo cáo kho liên quan nhập/xuất/tồn và giá trị kho |
| 8.7.1 Thống kê khách hàng đa chiều | Trỏ nhầm `/admin/reports/hourly` | `/admin/customers/reports/multidimensional` |

## Checklist khi thêm/sửa báo cáo menu 8.*

1. Xác định menu nào cùng mục đích ở role khác để dùng chung controller/service/page.
2. Navigation chỉ trỏ route nghiệp vụ thật, không trỏ nhầm sang `/admin/reports/hourly` hoặc template placeholder.
3. Data phải query từ bảng nghiệp vụ thật hoặc bảng snapshot/ETL thật, không hard-code HTML.
4. Seeder demo phải tạo dữ liệu nghiệp vụ liên quan, không tạo riêng object UI.
5. Bảng nhiều cột dùng wrapper scroll ngang bám sát bảng; trang dài dùng scroll dọc của page, không khóa scroll toàn body.
6. CSS parity chỉ import qua `resources/js/lib/pushsaleStyleRegistry.js`, tránh inline style rải rác cho các layout dùng chung.
