# Checklist: Login, phân quyền, thống kê và xếp hạng

## Mục tiêu

Chuẩn bị danh sách việc cần làm để push trước phần login và phân quyền, đồng thời nghiên cứu/thiết kế phần thống kê và xếp hạng có giao diện tốt cho ERM SaleOps.

## Phạm vi role

- [x] `admin` — quản trị riêng, toàn quyền hệ thống.
- [x] `sales` — sale/telesale, xử lý khách hàng và đơn được giao.
- [x] `marketing` — theo dõi nguồn lead, campaign, hiệu quả marketing.
- [x] `warehouse` — kho, tồn kho, xuất/nhập, vận đơn.

## 1. Login và auth

### Backend

- [x] Kiểm tra flow đăng nhập hiện tại dùng Laravel/Inertia.
- [x] Đảm bảo user đăng nhập bằng email/password.
- [x] Đảm bảo validate lỗi đăng nhập rõ ràng.
- [x] Đảm bảo logout hoạt động đúng.
- [x] Đảm bảo session/cookie không lỗi khi reload trang.
- [x] Kiểm tra redirect sau login theo role.
- [x] Kiểm tra route guest không cho user đã đăng nhập vào lại login.

### Frontend

- [x] Hoàn thiện UI màn `/login`.
- [x] Thêm trạng thái loading khi submit.
- [x] Hiển thị lỗi sai email/password.
- [ ] Kiểm tra responsive mobile/tablet/desktop.
- [ ] Đảm bảo form dùng keyboard tốt: Enter để submit, tab order đúng.

### Acceptance criteria

- [x] User đăng nhập thành công với tài khoản seed/demo.
- [x] User sai thông tin thấy lỗi dễ hiểu.
- [x] User đăng xuất quay về login.
- [x] Reload trang sau login không mất session.

## 2. Phân quyền theo role

### Role model / seed

- [x] Xác nhận danh sách role chính: `admin`, `sales`, `marketing`, `warehouse`.
- [x] Kiểm tra DB có field/quan hệ lưu role cho user.
- [x] Bổ sung seed user demo cho từng role.
- [x] Đặt password demo thống nhất nếu cần test nhanh.

### Middleware / route guard

- [x] Kiểm tra middleware role hiện tại.
- [x] Chặn route admin chỉ cho `admin`.
- [x] Chặn route sales chỉ cho `sales` hoặc admin nếu cần.
- [x] Chặn route marketing chỉ cho `marketing` hoặc admin nếu cần.
- [x] Chặn route warehouse chỉ cho `warehouse` hoặc admin nếu cần.
- [x] Trả về redirect/403 rõ ràng khi không có quyền.

### Frontend navigation

- [x] Sidebar hiển thị menu theo role.
- [x] User không thấy menu không có quyền.
- [x] User nhập URL trực tiếp vẫn bị backend chặn.
- [x] Topbar/UserMenu hiển thị tên và role hiện tại.

### Acceptance criteria

- [x] `admin` vào được toàn bộ màn quản trị.
- [x] `sales` chỉ thấy/vào được màn sale cần thiết.
- [x] `marketing` chỉ thấy/vào được màn marketing/thống kê liên quan.
- [x] `warehouse` chỉ thấy/vào được màn kho.
- [x] User không có quyền không thể bypass bằng URL.

## 3. Route và màn theo role

### Admin

- [x] `/admin/dashboard` — tổng quan hệ thống.
- [x] `/admin/reports/business` hoặc route báo cáo tổng hợp hiện có — thống kê business.
- [x] `/admin/organization` hoặc route xếp hạng nhân sự hiện có — xếp hạng/team.
- [ ] Quản lý user/role nếu đã có scope.

### Sales

- [x] `/sales/workspace` — danh sách việc cần gọi/chốt.
- [x] `/sales/customers` — hồ sơ khách hàng của sale.
- [ ] Báo cáo/xếp hạng cá nhân nếu cần.

### Marketing

- [x] `/admin/marketing/dashboard` — dashboard marketing.
- [x] `/admin/marketing/revenue` — doanh số theo nguồn/campaign.
- [ ] Xem ranking nguồn/campaign nếu có dữ liệu.

### Warehouse

- [x] `/admin/warehouse/operations` — tác nghiệp kho.
- [x] `/admin/warehouse/inventory` — tồn kho/sản phẩm.
- [ ] Thống kê đơn đã xử lý/xuất kho nếu có dữ liệu.

## 4. Thống kê

### Nghiên cứu dữ liệu cần hiển thị

- [ ] Xác định KPI tổng quan: doanh thu, đơn, tỉ lệ chốt, hoàn/hủy, COD, tồn kho.
- [ ] Xác định KPI marketing: lead, đơn từ campaign, doanh thu theo nguồn, CPA nếu có chi phí.
- [ ] Xác định KPI sales: số cuộc gọi, số đơn chốt, doanh thu, tỉ lệ chốt.
- [ ] Xác định KPI kho: đơn chờ xử lý, đã xuất, lỗi, tồn thấp.
- [ ] Xác định filter chung: ngày, nguồn, sale, marketing, sản phẩm, trạng thái.

### Backend/API

- [ ] Kiểm tra service/repository thống kê hiện có.
- [ ] Chuẩn hóa response cho dashboard summary.
- [ ] Thêm filter date range.
- [ ] Đảm bảo query không quá nặng khi dữ liệu lớn.
- [ ] Dùng pagination type hiện có nếu response dạng danh sách.

### Frontend/UI

- [ ] Thiết kế card KPI dễ đọc.
- [ ] Dùng chart phù hợp: line/bar/pie/table ranking.
- [ ] Có skeleton/loading state.
- [ ] Có empty state khi chưa có dữ liệu.
- [ ] Có error state khi API lỗi.
- [ ] UI dense nhưng rõ, theo style Enterprise SaaS Operations Dashboard.

### Acceptance criteria

- [ ] Dashboard thống kê load được dữ liệu thật/seed.
- [ ] Filter ngày hoạt động đúng.
- [ ] Số liệu khớp logic DB/service.
- [ ] Giao diện nhìn gọn, chuyên nghiệp, không rối.

## 5. Xếp hạng

### Ranking sales

- [ ] Xếp hạng theo doanh thu.
- [ ] Xếp hạng theo số đơn chốt.
- [ ] Xếp hạng theo tỉ lệ chốt.
- [ ] Có badge top 1/2/3.
- [ ] Có so sánh tăng/giảm so với kỳ trước nếu dữ liệu hỗ trợ.

### Ranking marketing

- [ ] Xếp hạng theo nguồn/campaign tạo doanh thu.
- [ ] Xếp hạng theo số lead.
- [ ] Xếp hạng theo tỉ lệ chuyển đổi lead → đơn.
- [ ] Có filter campaign/source/date.

### Ranking warehouse

- [ ] Xếp hạng theo số đơn xử lý.
- [ ] Theo dõi đơn lỗi/chậm xử lý.
- [ ] Có chỉ số cảnh báo tồn thấp hoặc pending cao.

### UI ranking

- [ ] Bảng ranking có rank, avatar/name, role/team, KPI chính, KPI phụ.
- [ ] Top performer có visual nổi bật nhưng không quá màu mè.
- [ ] Có sort/filter.
- [ ] Có responsive layout.

## 6. QA và kiểm thử thủ công

- [x] Test login/logout với từng role demo.
- [x] Test role không vào được route sai quyền.
- [x] Test menu hiển thị đúng theo role.
- [ ] Test dashboard thống kê khi có dữ liệu.
- [ ] Test dashboard thống kê khi không có dữ liệu.
- [ ] Test ranking với dữ liệu seed.
- [ ] Test responsive các màn chính.
- [x] Kiểm tra console không có lỗi frontend.
- [x] Kiểm tra backend log không có exception.

## 7. Thứ tự ưu tiên đề xuất

### P0 — push trước

- [x] Login ổn định.
- [x] Role `admin`, `sales`, `marketing`, `warehouse` rõ ràng.
- [x] Route guard backend.
- [x] Sidebar/menu theo role.
- [x] Seed user demo từng role.

### P1 — thống kê và xếp hạng bản đẹp

- [ ] Dashboard KPI tổng quan.
- [ ] Ranking sales/marketing/warehouse.
- [ ] Filter date range.
- [ ] UI card/chart/table hoàn thiện.

### P2 — nâng cấp sau

- [ ] So sánh kỳ trước.
- [ ] Export báo cáo.
- [ ] Realtime update.
- [ ] Drilldown từ KPI vào danh sách chi tiết.
- [ ] Cấu hình quyền chi tiết hơn theo permission.

## 8. Ghi chú triển khai

- Admin là role riêng, không gộp với sale/marketing/kho.
- Backend phải là lớp chặn quyền chính; frontend chỉ ẩn menu để UX tốt hơn.
- Phần thống kê nên ưu tiên số liệu cần cho vận hành thật, tránh làm chart chỉ để đẹp.
- Giao diện nên theo hướng dashboard nội bộ: rõ, nhanh, dense, ít animation.
