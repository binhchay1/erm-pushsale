# V99 — Security/report header + login permission/filter-history data

## Phạm vi sửa

- Chuẩn hóa header/filter cho nhóm báo cáo dùng `ps-report-v2-toolbar` để title nằm trái, filter/actions nằm phải và các lề giống contract Pushsale/AdminLTE.
- Chuẩn hóa riêng template 1.7.2 `Quản lý cho phép tài khoản đăng nhập`:
  - title nằm góc trái trong header;
  - ô tìm kiếm + nút tìm kiếm nằm góc phải;
  - filter đơn vị/quyền/user/trạng thái/sắp xếp nằm dưới header, không còn lệch padding;
  - bỏ KPI “Tổng bản ghi” tự render từ `summary.total_records` trên các trang table thường;
  - bỏ duplicate display column `actions`.
- Chuẩn hóa template 1.7.3 `Lịch sử lọc data chốt đơn`:
  - chỉ lấy log hành động lọc data thật (`ActivityLogger::DATA_FILTER_SEARCHED`);
  - render bảng đếm số lần lọc theo user ở cột phải;
  - seed full có dữ liệu demo để trang không trống.

## Backend/data

- `BasePushsalePageController` ghi activity log khi user filter/search ở các trang nghiệp vụ/báo cáo, trừ nhóm security `1.7.*` để tránh tự log trang log.
- `PushsalePageService::activityLogs('1.7.3')` chỉ đọc log lọc data và map đúng các cột: form lọc, trạng thái chốt, trạng thái giao, kiểu ngày/ngày lọc, user, ngày lọc.
- `PushsalePageService::loginPermissions()` đọc trực tiếp user thật và permissions thật:
  - status hiển thị theo mẫu: `Đã phê duyệt` / `Chưa phê duyệt`;
  - filter trạng thái dùng `_login_permission_status`: `2` approved, `1` pending/blocked.
- `DataFilterHistorySeeder` được thêm vào `DatabaseSeeder` để `php artisan migrate:fresh --seed` hoặc deploy seed full có sẵn log cho menu 1.7.3.
- `FullDemoSeedTest` assert full seed có activity log cho menu 1.7.3.

## Frontend/CSS

- CSS cuối chuỗi: `resources/css/pushsale-security-report-header-contract.css`.
- Đăng ký trong `resources/js/lib/pushsaleStyleRegistry.js` sau v98 để override contract cuối cùng.
- `BusinessPage.jsx`:
  - không render `LiveDataSummary` nếu summary chỉ có `total_records`;
  - map thêm filter key cho 1.7.2 `QuanLyDangNhap`;
  - map `txtUsername` thành search cho 1.7.3;
  - status badge nhận diện `Đã phê duyệt`/`Chưa phê duyệt`;
  - bảng user count của 1.7.3 được hydrate từ rows thật.

## Ghi chú test/seed

- `php artisan test` chạy test suite và seed dữ liệu trong DB test theo từng test case, không tự đổ demo vào DB staging/production đang chạy.
- Muốn tạo demo đủ toàn hệ thống cho DB đang chạy: dùng `php artisan migrate:fresh --seed` ở local/staging an toàn, hoặc deploy script với `STAGING_SEED_MODE=full`.
- `STAGING_SEED_MODE=accounts` chỉ tạo/cập nhật tài khoản đăng nhập tối thiểu, không tạo đủ dữ liệu nghiệp vụ.
