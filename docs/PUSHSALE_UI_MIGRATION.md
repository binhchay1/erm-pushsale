# Pushsale UI migration

## Mục tiêu

Đồng bộ lớp giao diện ERM với bộ HTML/ảnh tham chiếu Pushsale được cung cấp, nhưng giữ nguyên Laravel/Inertia/React, phân quyền, tenant scope và các service nghiệp vụ hiện có.

## Theme gốc đã xác định

Các file HTML tham chiếu khai báo trực tiếp:

- AdminLTE 2.3.0
- Bootstrap 3
- Font Awesome 4
- `skin-blue-light`
- Select2 3.x và Bootstrap Datepicker 3

Tài nguyên tĩnh đã được đưa vào `public/vendor/adminlte2` và `public/vendor/font-awesome`. ERM chỉ nạp CSS/font/image của theme. Không nạp jQuery, Angular cũ hoặc `AdminLTE app.js`, vì các script đó sẽ tranh quyền quản lý DOM với React/Radix/Inertia. Hành vi sidebar, dropdown, modal và trạng thái active được viết lại bằng React nhưng giữ class/DOM/CSS tương thích giao diện AdminLTE 2.

## Cây menu

`config/pushsale_navigation.php` chứa cây menu ba cấp lấy từ `menu navbar.txt`, gồm đủ 9 nhóm và đúng thứ tự/nhãn của Pushsale. `NavigationService` thực hiện:

1. Lọc nhóm theo vai trò hiện tại.
2. Đổi route admin sang route tương ứng của Sales/Marketing/Kho/Kế toán/Allocator.
3. Lọc tiếp theo permission tùy chỉnh của ERM.
4. Giữ mục chưa có business tương ứng ở trạng thái vô hiệu hóa, thay vì trỏ sai sang một nghiệp vụ khác.

## Mapping các trang mẫu chính

| Trang mẫu | Route ERM |
|---|---|
| Marketing dashboard | `/admin/marketing/dashboard` |
| Sale tác nghiệp | `/admin/sales/workspace` và `/sales/workspace` |
| Sale KPI | `/admin/reports/extra/sale-4` |
| Báo cáo công việc sale | `/admin/reports/extra/sale-1` |
| Bảng tổng hợp chốt đơn | `/admin/reports/extra/sale-2` |
| Báo cáo doanh số marketing | `/admin/reports/extra/marketing-1` |
| Báo cáo công việc marketing | `/admin/reports/extra/marketing-3` |
| Báo cáo kinh doanh hệ thống | `/admin/reports/extra/kho-2` |
| Báo cáo CEO V2 | `/admin/reports/ceo` |
| Thống kê trưởng nhóm | `/admin/reports/team-leaders` |
| Kế toán tác nghiệp | `/admin/accounting` |
| Kết nối Landing | `/admin/landing-approvals` |
| Kết nối Facebook | `/admin/integrations` |
| Danh sách sản phẩm kho | `/admin/warehouse/inventory` |


## Tách biệt giao diện công khai và giao diện nội bộ

`resources/views/app.blade.php` chỉ nạp Bootstrap 3/AdminLTE 2/Font Awesome ở các route nội bộ. Trang chủ công khai và trang đăng nhập dùng `public-app-body` cùng `resources/css/public-shell.css`, vì vậy CSS bảng, modal, input và reset của AdminLTE không còn ghi đè Tailwind ở bên ngoài hệ thống.

Header nội bộ dùng đúng cấu trúc ảnh tham chiếu: thương hiệu `TTGROUP2.ADMIN`, điểm bảo mật, biểu tượng thông báo, tên tài khoản/Premium và dropdown gồm Thông tin cá nhân, Thay đổi mật khẩu, Thoát.

## Các báo cáo dựng riêng theo ảnh

Các trang có ảnh/HTML tham chiếu không đi qua một bảng generic duy nhất nữa:

- Marketing dashboard: hai hàng bộ lọc, hai nhóm cột, tổng bộ lọc/tổng theo trang, conditional data bar và phân trang.
- Báo cáo công việc sale: hai hàng toolbar, nhóm cột Gọi lần 1–6, Chăm sóc lần 1–3 và Bỏ qua.
- Sale KPI 2: ma trận KPI bên trái và bảng Tiến độ thời gian bên phải.
- Danh sách sản phẩm kho: đúng thứ tự bộ lọc, nút tác vụ và cột bảng.
- Bảng tổng hợp chờ xuất theo ngày: title bar, filter bar và bảng Đầu kỳ/Chờ xuất/Xuất bán hàng/Cuối kỳ.

## Popup “Nhập đơn mới”

Popup mới nằm tại `resources/js/components/operations/CreateSaleOrderDialog.jsx` và được gắn vào màn Sale tác nghiệp. Form gửi qua luồng nhập lead/đơn thủ công hiện có, không tạo endpoint song song.

Dữ liệu được lưu đúng trường nghiệp vụ:

- khách hàng, số điện thoại, địa chỉ giao hàng;
- nguồn dữ liệu;
- tin nhắn và ghi chú giao hàng;
- kho và phương thức giao;
- sản phẩm/combo, số lượng, đơn giá, chiết khấu từng dòng;
- chiết khấu toàn đơn, phí vận chuyển thu khách, tiền đặt cọc;
- sale đang đăng nhập khi tạo từ workspace Sales.

`ManualLeadController`, `ManualLeadImportService` và `ManualLeadDriver` đã được mở rộng để bảo toàn các trường trên tới `LeadOrderFactory`.

## Màn hình kho theo ảnh tham chiếu

`resources/js/pages/Admin/Warehouse/Inventory.jsx` đã được dựng lại theo trang “Danh sách sản phẩm kho”: thanh tìm kiếm góc phải, bộ lọc kho/sản phẩm/vị trí/lô/trạng thái kinh doanh, nhóm nút nhập-xuất kho/cập nhật vị trí/xuất Excel và bảng tồn kho mật độ cao. Form nhập-xuất hiện có được chuyển vào modal để không làm thay đổi service nghiệp vụ. `WarehouseInventoryService` hỗ trợ thêm tìm SKU, vị trí, mã lô, trạng thái và số dòng mỗi trang.

## CSS adapter

`resources/css/pushsale-layout.css` là lớp adapter chính, bao gồm:

- header cao 50px, sidebar 252px/collapse 42px;
- menu sáng `skin-blue-light`, cấp 2 mở trong sidebar và cấp 3 mở flyout xanh bên phải đúng ảnh mẫu;
- bảng 11–12px, header xanh, đường viền và zebra row giống mẫu;
- filter bar, button, input, select, pagination kiểu Bootstrap 3;
- card/box vuông, bỏ radius/shadow kiểu dashboard hiện đại;
- modal Radix được render giống Bootstrap 3 modal;
- popup nhập đơn rộng riêng, không làm thay đổi kích thước các dialog khác;
- responsive sidebar cho tablet/mobile.

## Kiểm tra đã chạy

```bash
php -l app/Http/Controllers/Sales/OperationController.php
php -l app/Http/Controllers/Admin/ManualLeadController.php
php -l app/Integrations/Manual/ManualLeadDriver.php
php -l app/Services/Leads/ManualLeadImportService.php
php -l app/Services/NavigationService.php
php -l config/pushsale_navigation.php
php -l routes/web.php
npm run build
```

Vite production build hoàn tất với 3.361 module. Môi trường đóng gói không có `composer`/`vendor`, vì vậy route runtime và PHPUnit cần chạy tiếp trên máy deploy sau `composer install`.
