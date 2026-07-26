# V92 — Dashboard/menu/report doanh số chi tiết sale

## Phạm vi

Bản này tiếp tục từ V91, tập trung vào 3 nhóm lỗi người dùng báo:

1. Dashboard toolbar chưa thẳng hàng; dashboard các role cần đồng bộ theo hướng Admin dashboard.
2. Sidebar menu đang đậm, font/spacing lớn hơn mẫu Pushsale.
3. Báo cáo doanh số chi tiết sale cần bám mẫu Pushsale.vn nhưng bổ sung nghiệp vụ upsale của ERM Pushsale.

## Dashboard theo role

Đã thêm class wrapper chung `ps-role-dashboard ps-role-dashboard-{role}` trong `RoleDashboardShell` để các dashboard role dùng chung contract spacing/header. Các role hiện có dashboard riêng:

- Admin: điều hành doanh thu, tiền thu, COD, phí vận chuyển, chi phí marketing, giá vốn, nhân sự, lợi nhuận, trạng thái realtime.
- Sales/Telesale: lead/contact được phân, tác nghiệp cần làm, đơn chốt, tỷ lệ chốt, doanh thu, lịch hẹn/tác nghiệp tiếp.
- Marketing: nguồn dữ liệu, ngân sách, contact, tỷ lệ contact, đơn chốt, doanh thu, ROAS/hiệu quả nguồn.
- Warehouse/Kho: đơn cần đăng, đơn đã đăng, trạng thái vận chuyển, tồn kho thấp, lỗi mã vận đơn/in phiếu.
- Accounting/Kế toán: COD chưa đối soát, COD đã thu, lệch COD, phí hoàn/phí vận chuyển, đối soát pending.
- Allocator/Chia số: lead mới, lead chờ chia, lead lỗi, trùng số, phân bổ thành công.

## Menu

CSS V92 làm menu nhẹ hơn để sát mẫu Pushsale:

- sidebar width 252px;
- root item 42px, font 13px;
- child item 40px, font 13px;
- bỏ border/hairline xanh nhạt ở menu cấp 3;
- menu cấp 3 nền xanh, hover xanh đậm, không underline.

## Báo cáo doanh số chi tiết sale

Trang `/admin/sales/revenue` đã bỏ `ReportFilterBar` generic, chuyển sang `PushsalePageShell` với:

- title + filter chính cùng 1 hàng;
- filter nâng cao bằng nút mũi tên;
- chú giải công thức dạng 3 cột giống Pushsale;
- bảng 2 tầng header như Pushsale;
- bổ sung cột upsale: `Upsale (SL)`, `Upsale (DS)`, `% DS upsale`.

Backend `RevenueMetricsCalculator` đã thêm metric:

- `upsellQuantity`: tổng quantity của order_items có `item_type=upsell` hoặc origin chứa `upsell/upsale` trong đơn đã chốt.
- `upsellRevenue`: tổng lineTotal của các dòng upsale.
- `upsellRevenueShare`: upsellRevenue / doanh số đơn chốt.

Doanh số đơn tổng vẫn là `Order::netRevenue()` để tránh tách đôi đơn; upsale là lát cắt riêng trong cùng đơn.

## Test mới

`tests/Feature/Reports/RevenueReportServiceTest.php` có thêm test kiểm tra báo cáo sale trả đúng:

- đơn chốt = 1;
- doanh số đơn = 458.000;
- số sản phẩm = 2;
- upsale SL = 1;
- upsale DS = 159.000;
- % DS upsale = 34.7%.
