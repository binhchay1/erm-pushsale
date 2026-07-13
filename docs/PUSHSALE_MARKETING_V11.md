# Pushsale Marketing V11

## Phạm vi

Bản V11 triển khai hai màn hình từ `template-third.zip`:

- `2.1 Marketing dashboard`
- `2.2 Bảng xếp hạng`

Các file ảnh/TXT trong hai thư mục template được dùng để đối chiếu cấu trúc HTML, CSS, bộ lọc, bảng, modal và trạng thái tương tác. Dữ liệu mẫu có trong HTML capture không được đưa vào runtime.

## Mapping template

### Marketing dashboard

- `marketing dashboard/marketing dashboard.txt`
- `marketing dashboard/marketing dashboard.png`
- `marketing dashboard/biểu đồ dữ liệu.txt`
- `marketing dashboard/biểu đồ dữ liệu.png`
- `marketing dashboard/thêm dữ liệu theo ngày.txt`
- `marketing dashboard/thêm dữ liệu theo ngày.png`

Các thành phần đã triển khai:

- Thanh tiêu đề và hai hàng bộ lọc Pushsale.
- Bảng nhóm cột `THÔNG TIN NGUỒN DỮ LIỆU` và `THÔNG TIN HIỆU QUẢ MARKETING`.
- Cây mở rộng Sản phẩm/Nguồn dữ liệu → UTM Source → UTM Campaign.
- Chế độ UTM nâng cao hiển thị thêm UTM Medium, UTM Term và UTM Content lấy từ payload lead thực tế.
- Hàng tổng theo bộ lọc và tổng theo trang.
- Modal biểu đồ dữ liệu.
- Modal thêm dữ liệu theo ngày.
- Export CSV, phân trang, lựa chọn số dòng và giữ nguyên bộ lọc khi chuyển trang.

### Bảng xếp hạng

- `bảng xếp hạng/bảng xếp hạng.txt`
- `bảng xếp hạng/bảng xếp hạng-đầu trang.png`
- `bảng xếp hạng/bảng xếp hạng-cuối trang.png`

Các thành phần đã triển khai:

- Bộ lọc khoảng ngày, cách tính chiết khấu, phạm vi tác nghiệp, trưởng nhóm và nhóm Marketing.
- Top 10 dạng bậc chéo theo ảnh gốc.
- Avatar thật hoặc chữ viết tắt của nhân sự.
- Bảng thống kê khách mới, khách cũ và tổng doanh thu.
- Tổng cộng toàn bộ kết quả, phân trang, in và làm mới.

## Route và controller

### Admin

- `GET /admin/marketing/dashboard`
- `GET /admin/marketing/dashboard/chart`
- `GET /admin/marketing/dashboard/daily-metrics`
- `PUT /admin/marketing/dashboard/daily-metrics`
- `GET /admin/marketing/dashboard/export`
- `GET /admin/rankings`

### Marketing workspace

- `GET /marketing/workspace`
- `GET /marketing/workspace/chart`
- `GET /marketing/workspace/daily-metrics`
- `PUT /marketing/workspace/daily-metrics`
- `GET /marketing/workspace/export`
- `GET /marketing/rankings`

Controller chính:

- `app/Http/Controllers/Admin/Marketing/DashboardController.php`
- `app/Http/Controllers/Admin/Marketing/DashboardDataController.php`
- `app/Http/Controllers/Admin/RankingController.php`
- `app/Http/Controllers/Marketing/RankingController.php`

Component:

- `resources/js/pages/Admin/Marketing/Dashboard.jsx`
- `resources/js/pages/Admin/Marketing/Ranking.jsx`

CSS:

- `resources/css/pushsale-marketing.css`

## Mapping dữ liệu thật

### Contact

Contact được đếm từ `lead_ingestions` sau khi áp dụng `LeadContactMetrics::applyCountableScope()`. Cách này loại các packet không nên được tính như một contact kinh doanh độc lập theo quy tắc hiện có của hệ thống.

Phân bổ contact cho Marketing theo thứ tự ưu tiên:

1. `orders.marketer_user_id` của đơn liên quan.
2. `marketing_sources.marketer_user_id`.
3. Marketer của nguồn cha khi nguồn là nguồn con.

### Đơn chốt và doanh thu

Đơn chốt lấy từ `orders` khi:

- Có `closed_at`; hoặc
- `closing_status = closed` đối với dữ liệu cũ chưa có `closed_at`.

Ngày lọc ưu tiên `closed_at`; dữ liệu cũ dùng `updated_at` khi đã ở trạng thái chốt.

Doanh thu sau chiết khấu dùng `Order::effectiveRevenue()`. Chế độ trước chiết khấu dùng `subtotal` hoặc tổng `quantity × unit_price` từ `order_items` khi cần đối chiếu.

### Khách mới và khách cũ

Phân loại từ `orders.is_returning_customer`:

- `false`: khách mới.
- `true`: khách cũ.

Contact chưa có order được xem là khách mới vì chưa có bằng chứng lịch sử mua lại trên đơn.

### Ngân sách và số click

Dữ liệu theo ngày được lưu tại bảng:

- `marketing_source_daily_metrics`

Khóa logic gồm:

- Công ty.
- Nguồn dữ liệu.
- Ngày.
- UTM Source.
- UTM Campaign.

Các trường:

- `budget`
- `clicks`
- Người tạo/cập nhật
- Thời điểm cập nhật

Khi chưa có dữ liệu theo ngày, dashboard dùng số tổng hợp cũ trên `marketing_sources` làm fallback để không làm mất dữ liệu đang vận hành. Sau khi nhập số liệu ngày, số liệu ngày được ưu tiên.

### Công thức chính của Marketing dashboard

- `Tỷ lệ contact/tương tác = contact / tương tác × 100`.
- `Giá contact = ngân sách / contact`.
- `Tỷ lệ chốt đơn = đơn chốt / contact × 100`.
- `Sản phẩm/đơn = số lượng sản phẩm / đơn chốt`.
- `NS/Doanh số = ngân sách / doanh số × 100`.
- `Doanh số sau CK = doanh thu hiệu lực sau chiết khấu`.
- `NS/Doanh số trừ CK = ngân sách / doanh số sau CK × 100`.

Khi mẫu số bằng 0, UI hiển thị `0`, `∞` hoặc trạng thái phù hợp theo cột thay vì phát sinh lỗi chia cho 0.

### Công thức Bảng xếp hạng

Mỗi nhân sự Marketing có:

- Contact mới/cũ.
- Đơn chốt mới/cũ.
- Tỷ lệ chốt mới/cũ.
- Số sản phẩm mới/cũ.
- Doanh thu mới/cũ.
- Tổng doanh thu.
- Chiết khấu.
- COD đã thu từ `shipping_fee_collected`.
- Phí COD/dịch vụ từ `cod_fee + carrier_service_fee`.
- Doanh thu cuối = `doanh thu mới + doanh thu cũ + COD đã thu - phí COD/dịch vụ`, tối thiểu bằng 0.

Top 10 được sắp xếp theo doanh thu cuối giảm dần.

## Modal thêm dữ liệu theo ngày

Modal tải danh sách nguồn và số liệu thật bằng JSON endpoint. Mỗi dòng cho phép cập nhật:

- Ngày.
- Nguồn dữ liệu.
- UTM Source.
- UTM Campaign.
- Ngân sách.
- Số click.

Backend validate, tenant-scope và ghi theo transaction. Mỗi lần cập nhật được ghi `activity_logs` với action `marketing_daily_metrics_updated`.

Không có row demo hoặc giá trị ngân sách/click tự sinh.

## Modal biểu đồ

Biểu đồ lấy dữ liệu theo ngày từ backend cho đúng nguồn/UTM đang chọn. Khoảng hiển thị tối đa 31 ngày gần nhất để tránh payload quá lớn. Các series gồm ngân sách, contact, đơn chốt và doanh thu.

## Phân trang

Cả hai trang dùng phân trang phía backend:

- Marketing dashboard phân trang theo nhóm nguồn gốc; các dòng UTM con đi cùng nguồn cha.
- Bảng xếp hạng phân trang theo nhân sự sau khi xếp hạng.
- Bộ lọc được giữ nguyên khi chuyển trang.
- Hỗ trợ số dòng `10 / 20 / 50 / 100`.

## Migration

Migration mới:

```text
database/migrations/2026_07_13_030000_create_marketing_source_daily_metrics_table.php
```

Triển khai:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

## Smoke test trên production

```bash
php artisan route:list --path=admin/marketing/dashboard
php artisan route:list --path=admin/rankings
php artisan migrate:status | grep 2026_07_13_030000
```

Kiểm tra trên trình duyệt:

1. Mở Marketing dashboard và lọc theo ngày/nhóm/marketer.
2. Mở một nguồn để kiểm tra UTM Source và UTM Campaign.
3. Bật `UTM Nâng cao`.
4. Mở modal thêm dữ liệu, lưu ngân sách/click, tải lại trang và kiểm tra số liệu.
5. Mở biểu đồ từ đúng dòng nguồn/UTM.
6. Export CSV và so sánh với bộ lọc hiện tại.
7. Mở Bảng xếp hạng, đổi cách tính trước/sau chiết khấu và kiểm tra thứ hạng.

## Giới hạn kiểm thử của gói bàn giao

Môi trường build không có `vendor` Composer và không kết nối RDS production. Vì vậy đã kiểm tra build frontend, PHP syntax, cấu trúc route tĩnh, migration và ZIP; chưa chạy `artisan route:list`, PHPUnit hoặc truy vấn trực tiếp trên dữ liệu production trong môi trường đóng gói.
