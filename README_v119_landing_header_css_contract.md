# v119 – Landing 2.4.1 + header/filter/menu CSS contract

Bản này tiếp tục từ v118 và tập trung ổn định các phần giao diện Pushsale đang bị lệch cascade.

## Phạm vi sửa

1. **Menu cấp 2 không có submenu cấp 3**
   - Các dòng như `1.4 Kết nối giao hàng`, `1.5 Phân bổ data`, `1.6 Cấu hình chức năng`, `2.1`, `2.2`, `2.3` dùng chung hover xanh/trắng với các dòng có submenu cấp 3.
   - Rule cuối nằm trong `resources/css/pushsale-unified-page-shell-contract.css`, block `v119`.

2. **Header/filter chuẩn dùng chung**
   - Thêm contract `ps-page-header-v119` gồm:
     - `ps-page-header-main`: chia 2 vùng title + filter chính.
     - `ps-page-primary-filters`: các filter chính ở bên phải.
     - `ps-page-advanced-filters`: filter phụ mở bằng nút mũi tên.
   - Trang 2.4.1 đã dùng contract này; các trang tiếp theo nên chuyển dần sang cùng class thay vì tự viết header riêng.

3. **Trang 2.4.1 Kết nối landing / nguồn dữ liệu**
   - Tab đúng thứ tự Pushsale: Kết nối Facebook → Kết nối nguồn dữ liệu → Kết nối Website → Tất cả.
   - Nút xóa tự động tách về phía phải, không dính liền cụm tab.
   - Cột cuối có nút `+ Thêm` rõ màu trắng trên nền xanh table header.
   - Header filter dùng `PushsaleSelect` đồng bộ, có ô search trong dropdown.

4. **Dialog thêm/sửa nguồn dữ liệu**
   - Sửa checkbox `Nhập thủ công` và `Duyệt` không lệch hàng.
   - Sản phẩm chuyển sang `PushsaleSelect` thống nhất với các filter khác.
   - Giữ thêm field `Upsale URL` không bắt buộc.

5. **Seed dữ liệu thật cho menu 2.4.1**
   - Thêm `LandingConnectionDemoSeeder`.
   - Seeder tạo qua `LandingConnectionManager`, nên có đủ: `marketing_sources`, `landing_connections`, `landing_connection_sources`, `landing_connection_products`, `landing_connection_sales`.

## Lưu ý khi copy source

Không xóa các thư mục asset legacy sau vì nhiều trang Pushsale vẫn đang reference:

- `public/vendor/adminlte2`
- `public/vendor/font-awesome`
- `public/build` chỉ được thay bằng build mới sau khi chạy `pnpm build`

Nếu cần reset repo bằng zip, copy toàn bộ zip vào working tree rồi chạy lại build, không tự xóa riêng vendor CSS/icon.
