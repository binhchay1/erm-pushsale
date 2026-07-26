# Pushsale Template V8 — AdminLTE 2 + ERM live modules

## 1. Quy ước bộ template

Bộ nguồn đối chiếu là `template(2).zip`:

- 141 file tổng cộng.
- 69 file `.txt` chứa phần HTML của nội dung trang/dialog.
- 72 file `.png` dùng để đối chiếu giao diện.
- 63 mã menu độc lập.

Tên trước hậu tố là mã trang. Ví dụ:

- `1.2.1.txt` + `1.2.1.png`: trang **Danh sách nhân viên**.
- `1.3.1-dialog-create.*`: dialog tạo mới thuộc trang `1.3.1`.
- `8.5.9-phía trên của trang.png` và `8.5.9-phía dưới của trang.png`: hai ảnh mô tả cùng trang `8.5.9`.

Các hậu tố không được đăng ký thành module hoặc URL độc lập.

## 2. Kiến trúc giao diện

Shell nội bộ dùng các primitive của Bootstrap 3/AdminLTE 2:

- Header cố định.
- Sidebar overlay mặc định đóng.
- Menu ba cấp và flyout.
- `box`, `small-box`, `info-box`, bảng, pagination, form control và modal.
- Font Awesome 4, Select2 và Bootstrap Datepicker.

Tài nguyên được đặt trong:

```text
public/vendor/adminlte2
public/vendor/font-awesome
```

`resources/js/lib/uiShellStyles.js` nạp tài nguyên vendor trước khi render shell. CSS React/Pushsale được đưa về cuối cascade để giữ override theo từng trang.

## 3. Tái sử dụng module ERM hiện có

Các menu sau không tạo backend thứ hai. Chúng trỏ vào module ERM thật và chỉ thay giao diện cho đúng template Pushsale:

| Mã | Menu | Module thật | URL |
|---|---|---|---|
| 1.2.1 | Danh sách nhân viên | UserController / users | `/admin/users` |
| 1.2.2 | Quản lý đội, nhóm | TeamController / teams | `/admin/teams` |
| 1.3.1 | Quản lý sản phẩm | ProductController / products | `/admin/products` |
| 2.3 | Hồ sơ khách hàng Marketing | CustomerProfileController | `/admin/marketing/customers` |
| 3.1 | Quản lý khách hàng | CustomerProfileController | `/admin/customer-management` |
| 4.2 | Hồ sơ khách hàng Telesale | CustomerProfileController | `/admin/sales/customers` |
| 5.1 | Đăng đơn / tác nghiệp vận đơn | Warehouse Operations | `/admin/warehouse/operations` |
| 5.2.1 | Danh sách kho | WarehouseController | `/admin/warehouses` |
| 5.2.2 | Danh sách sản phẩm kho | Warehouse Inventory | `/admin/warehouse/inventory` |

Mapping kiểm tra nằm tại:

```text
config/pushsale_page_merges.php
```

Mỗi menu vẫn giữ `activeMenuCode` riêng. Những route cũ theo slug template chỉ redirect sang URL nghiệp vụ thật.

## 4. Các trang còn lại

Các mã còn lại có route semantic riêng trong:

```text
config/pushsale_routes.php
routes/pushsale_pages.php
```

Mỗi trang có:

- Controller/action riêng.
- Schema filter/cột/dialog riêng.
- Service query dữ liệu thật.
- Component Inertia riêng.
- Template đã làm sạch trong `public/pushsale-templates`.

HTML capture chỉ cung cấp cấu trúc và class. Dòng dữ liệu capture, script cũ, ASP.NET postback, AngularJS và widget bên ngoài không được chạy trong ứng dụng.

## 5. Nhân sự

Trang `1.2.1` sử dụng bảng `users`, `teams`, roles/permissions và bảng bổ sung:

```text
user_operational_profiles
```

Bảng này lưu các trường vận hành chưa có trong user gốc:

- Mã nhân viên.
- Lương cứng.
- Ca làm việc.
- Cho phép nhận data.
- Trạng thái khóa.

Các thao tác đổi trạng thái nhận data, khóa tài khoản, sửa người dùng, đổi mật khẩu và xóa đều gọi backend thật.

Trang `1.2.2` sử dụng `teams`, trưởng nhóm, nhóm cha và danh sách thành viên thật.

## 6. Sản phẩm

Trang `1.3.1` dùng `products`, phân loại, thuộc tính, giá trị thuộc tính và combo thật. Các dialog trong template được gom vào một component trang:

- Tạo/sửa sản phẩm.
- Phân loại sản phẩm.
- Thuộc tính sản phẩm.
- Giá trị thuộc tính.
- Import CSV.

## 7. Kho

Các màn kho tái sử dụng service tồn kho, movement và tác nghiệp đơn hiện tại:

- Kho và người quản lý kho.
- Tồn kho/lô/hạn sử dụng/vị trí.
- Chờ xuất.
- Nhập hàng.
- Xuất CSV.
- Xóa dòng tồn kho theo quyền.
- Đơn kho và vận đơn.

## 8. Hồ sơ khách hàng và upsale

Ba menu hồ sơ khách hàng dùng cùng một service nghiệp vụ, nhưng URL và active menu độc lập.

Filter được xử lý ở backend theo dữ liệu đơn, lead, nguồn marketing, sale/team, kho, sản phẩm, tác nghiệp, giao hàng và đối soát. Các dialog gọi endpoint thật:

- Lịch sử tác nghiệp.
- Tin nhắn nội bộ.
- Chat Pancake.
- Lịch sử mua hàng.
- Hồ sơ đầy đủ.

Luồng upsale hiện có vẫn được giữ nguyên: idempotency, hold window, orphan/late review, đơn bổ sung, khóa đơn và audit packet.

## 9. Kiểm tra

Chạy audit tĩnh:

```bash
php scripts/audit_pushsale_v8.php
```

Audit kiểm tra:

- 65 page registry.
- 9 menu hợp nhất module ERM.
- 69 template đã làm sạch.
- Controller/component/route tương ứng.
- Semantic URL.
- Tài nguyên AdminLTE 2.
- Không còn script thực thi trong fragment.
- Không còn bảng generic hoặc số báo cáo demo trong runtime service.

## 10. Triển khai

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

`public/build` đã được build sẵn trong gói bàn giao.
