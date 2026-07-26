# V45 — Marketing Leader / Thống kê trưởng nhóm

## Quy ước nghiệp vụ đã chốt
- Route Pushsale trong ảnh/source chỉ dùng để nhận diện màn hình gốc.
- Project ERM không cần giữ nguyên route Pushsale; backend/controller/permission đi theo cấu trúc ERM.
- UI/UX và chức năng nhìn thấy được phải bám sát Pushsale/AdminLTE 2.
- Không copy-paste raw HTML Pushsale. Phân tích cấu trúc, tách component, dùng service/backend thật.

## Màn đã xử lý
Menu 2.8.1 Marketing Leader → Thống kê trưởng nhóm.

## Source tham chiếu
File `Pasted text(8).txt`, title gốc: `Thống kê trưởng nhóm`.

## Backend
- `TeamLeaderStatsService` được rewrite để tính dữ liệu thật theo marketer:
  - contacts từ lead countable qua `LeadContactMetrics`;
  - chốt đơn chỉ tính đơn đã chốt từ contact gốc;
  - doanh số KHM/KHC/tổng, COD, hỗ trợ COD, CK, đặt cọc, doanh số sau CK, KPI;
  - summary trạng thái giao hàng: chờ giao, hủy vận đơn, đang giao, đã giao, đã thanh toán, đã hoàn.
- Dùng `ReportQueryService` để giữ scope/tenant/permission/filter chung.
- Dùng `MarketingBudgetService` để lấy ngân sách thực/kế hoạch theo nguồn.
- Dùng `MonthlyKpiPlan` để lấy KPI doanh số marketing.

## Frontend
- `resources/js/pages/Reports/TeamLeaderStats.jsx` được rebuild theo Pushsale:
  - header/filter hai dòng;
  - cards trạng thái giao hàng;
  - bảng grouped header: KHÁCH HÀNG MỚI / KHÁCH HÀNG CŨ / TỔNG CHUNG;
  - progress bar màu theo từng loại metric như ảnh Pushsale.
- CSS thêm trong `resources/css/pushsale.css`, scoped dưới `.ps-marketing-leader-page`, tái dùng `.ceo-report-pushsale` và component chung.

## Route
- Route chính hiện có: `/admin/reports/team-leaders`, `/marketing/reports/team-leaders`.
- Thêm alias đối chiếu source: `/ld/marketing/thong-ke-truong-nhom`.
