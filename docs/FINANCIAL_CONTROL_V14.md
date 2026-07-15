# V14 — Kiểm soát dòng tiền, ngân sách Landing và Admin Dashboard

## 1. Mục tiêu nghiệp vụ

V14 chuyển Admin Dashboard thành bảng điều hành tài chính xuyên suốt từ Marketing → Lead → Sale → Kho → Vận chuyển → Kế toán. Toàn bộ giá trị tiền trong hệ thống được lưu và hiển thị theo **VND**, không dùng dữ liệu mẫu trong HTML template.

Các số liệu trên dashboard đều truy vấn từ bảng nghiệp vụ thật và tách rõ các khái niệm để tránh cộng doanh thu hoặc chi phí hai lần.

## 2. Ngân sách trên Kết nối Landing

Mỗi `landing_connection` có thêm:

- `budget_type`: `total` hoặc `daily`;
- `budget_amount`: số nguyên VND;
- `budget_start_date`;
- `budget_end_date`.

Quy tắc:

- Ngân sách tổng được chia chính xác theo từng ngày của kỳ. Phần dư làm tròn được phân bổ vào các ngày cuối, nên tổng ngày luôn bằng đúng tổng ngân sách.
- Ngân sách theo ngày bằng `budget_amount × số ngày giao nhau`.
- Có thực chi trong `marketing_source_daily_metrics` thì thực chi ghi đè đúng nguồn/ngày đó.
- Nguồn/ngày chưa nhập thực chi dùng ngân sách kế hoạch làm fallback.
- Dashboard trả `marketing_basis`: `actual`, `mixed`, `planned` hoặc `none` để người quản trị biết số liệu đang dựa trên nguồn nào.

## 3. Các lớp doanh thu

### Doanh số đã chốt

Tổng giá trị cuối của các đơn đã có `closed_at`. Chỉ số này cho biết Sale đã xác nhận bao nhiêu doanh số, chưa khẳng định hàng đã giao hoặc tiền đã về.

### Doanh thu ghi nhận

Tổng giá trị đơn thuộc trạng thái giao hàng đủ điều kiện ghi nhận doanh thu theo `DeliveryStatus::revenueEligible()`.

### Tiền đã thu

`min(giá trị đơn, tiền cọc + COD đã đối soát)` trên các đơn đã chốt. Cách tính này không cho phép thu tiền vượt doanh số đơn.

### COD chưa thu

`amount_to_collect - settled_cod_amount`, chỉ tính phần dương và loại các trạng thái hủy/hoàn không còn phải thu.

## 4. Các lớp chi phí

### Marketing

Chi phí hiệu lực theo ngân sách/thực chi của các nguồn gắn với Kết nối Landing trong kỳ.

### Giá vốn hàng bán

Giá vốn được snapshot vào `order_items.cost_price` khi dựng đơn. Báo cáo ưu tiên snapshot để việc sửa giá vốn sản phẩm sau này không làm thay đổi lịch sử. Dữ liệu cũ chưa có snapshot mới fallback sang `products.cost_price`.

Với combo không có giá vốn trực tiếp, giá vốn được tính từ tổng `số lượng thành phần × giá vốn thành phần`.

### Vận chuyển

Tổng phí dịch vụ hãng vận chuyển, phí COD, phần hỗ trợ vận chuyển và hỗ trợ COD trên các đơn đủ điều kiện ghi nhận doanh thu.

### Nhân sự

Nguồn dữ liệu là `monthly_kpi_plans`:

- Lương cơ bản theo ngày công khi kế hoạch đã khóa hoặc đã nhập `actual_days`.
- Kế hoạch chưa khóa và chưa nhập ngày công được tạm tính đủ lương tháng; dashboard phát cảnh báo rõ đây là chi phí dự kiến.
- Thưởng/hoa hồng = doanh số đóng đơn đúng tháng × `bonus_percent`.
- Tổng lương/thưởng tháng được phân bổ chính xác theo từng ngày để báo cáo khoảng ngày không bị lệch do làm tròn.

Màn KPI tháng và Admin Dashboard dùng chung `PayrollCostService`, nên lương, thưởng và thu nhập không còn lệch công thức.

### Chi phí vận hành khác

Từ bảng `expenses`, phân bổ theo từng ngày của tháng. Nếu nội dung chi phí có từ khóa lương/thưởng/hoa hồng trong khi hệ thống đã tính nhân sự từ KPI, dashboard cảnh báo nguy cơ nhập trùng.

## 5. Công thức lợi nhuận

```text
Lợi nhuận gộp
= Doanh thu ghi nhận
- Giá vốn
- Phí vận chuyển
- Chi phí Marketing

Lợi nhuận ròng
= Lợi nhuận gộp
- Chi phí nhân sự
- Chi phí vận hành khác
```

Giá trị tồn kho là `tồn dương × giá vốn hiện tại` theo từng dòng tồn kho.

## 6. Hiệu quả theo Kết nối Landing

Mỗi dòng kết nối hiển thị:

- ngân sách kế hoạch;
- thực chi đã nhập;
- tỷ lệ sử dụng ngân sách;
- chi phí thực dùng trong báo cáo;
- ngân sách còn lại;
- lead hợp lệ;
- đơn đã chốt;
- doanh thu ghi nhận;
- CPL, CPA và ROAS.

Order giữ `landing_connection_id`, Marketing Source và nguồn Landing chính. Packet upsale giữ source thực tế để có thể drill-down đến đúng trang tạo ra dữ liệu.

## 7. Chuỗi dữ liệu khép kín

```text
Tạo Kết nối Landing + nguồn + sản phẩm/gói + Sale + ngân sách
→ khách submit Landing chính
→ backend dựng item/giá, tạo lead/customer/order
→ upsale nối đúng flow và cộng item
→ Sale gọi và chốt
→ kiểm tra tồn
→ tạo vận đơn và trừ tồn idempotent
→ hãng vận chuyển cập nhật trạng thái/COD
→ doanh thu, giá vốn, vận chuyển, Marketing, lương và chi phí vận hành cùng vào dashboard
```

Không có bước nào dùng giá/số lượng/chi phí do client gửi để tính tiền.

## 8. Giao diện template-five

- File `.txt` được làm sạch và scope CSS để không ghi đè layout/modal toàn hệ thống.
- File PNG chỉ dùng làm ảnh đối chiếu giao diện.
- Hậu tố `dialog`, `modal`, `đầu trang`, `cuối trang` được gộp vào đúng trang gốc.
- Dữ liệu bảng, filter, CRUD và dialog lấy qua model/service/backend thật.
- Template không được phép mang dữ liệu mẫu hoặc script ASP.NET/DNN sang runtime.

## 9. Các file chính

- `app/Services/Reports/AdminFinancialDashboardService.php`
- `app/Services/Finance/PayrollCostService.php`
- `app/Services/Marketing/MarketingBudgetService.php`
- `resources/js/pages/Admin/Dashboard.jsx`
- `resources/css/pushsale-admin-finance-dashboard.css`
- `resources/js/pages/Pushsale/Pages/Page_2_4_1.jsx`
- `database/migrations/2026_07_14_010000_add_budget_control_to_landing_connections.php`
- `database/migrations/2026_07_14_020000_add_cost_snapshot_to_order_items.php`
- `scripts/build_pushsale_templates.py`
