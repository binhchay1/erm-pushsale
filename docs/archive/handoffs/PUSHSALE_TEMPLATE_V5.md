# Pushsale template integration V5

## Quy ước nguồn template

Mỗi mã file là một màn hình nghiệp vụ độc lập và trùng với mã menu navbar:

- `1.2.1.txt` chứa phần HTML nội dung của trang `1.2.1`.
- `1.2.1.png` là ảnh đối chiếu giao diện của cùng trang.
- Các hậu tố `-dialog-*`, `-modal-*`, `-đầu trang`, `-cuối trang` là dialog hoặc ảnh bổ sung của trang gốc, không phải module riêng.

HTML nguồn chỉ được đặt vào vùng content của layout chung. Header, sidebar, ngôn ngữ, notification và tài khoản được quản lý bởi React layout của ERM.

## Kiến trúc

Mỗi mã menu có:

1. URL tĩnh trong `routes/pushsale_pages.php`.
2. Controller riêng trong `app/Http/Controllers/Admin/Pushsale/Pages`.
3. Component Inertia riêng trong `resources/js/pages/Pushsale/Pages`.
4. Schema cột/filter/dialog riêng trong `config/pushsale_pages.php`.
5. Query nghiệp vụ trong `PushsalePageService`.
6. Resource CRUD riêng trong `config/pushsale_resources.php` khi màn hình có thao tác ghi.
7. Fragment đã làm sạch trong `public/pushsale-templates`.

Không có route wildcard `/admin/legacy/{code}` và không có bảng JSON dùng chung cho các trang.

## Layout

- Sidebar đóng mặc định.
- Hamburger mở sidebar dạng overlay 252px.
- Sidebar không thay đổi chiều rộng vùng nội dung.
- Chọn menu hoặc chuyển trang sẽ đóng sidebar.
- Active menu ưu tiên `activeMenuCode`, sau đó mới so khớp pathname chính xác.
- Menu cấp ba mở flyout bên phải theo giao diện Pushsale.
- Shell public/login và shell nội bộ có Inertia version khác nhau để bắt buộc tải lại document khi đăng nhập/đăng xuất, tránh dashboard thiếu CSS cho đến khi F5.

## Backend và dữ liệu

Các trang dùng model nghiệp vụ thật như user, team, product, order, marketing source, warehouse và inventory. Những nghiệp vụ trước đây chưa có bảng đã được tạo thành bảng chuyên biệt, gồm ca làm việc, phân bổ data, quyền báo cáo, quy trình tác nghiệp, chiết khấu COD, blacklist, chiến dịch chăm sóc, phiếu kho, chi phí, hóa đơn điện tử, ánh xạ Facebook Page và kết nối đối tác.

Các trang có cùng mục đích có thể dùng chung query service, nhưng vẫn giữ URL, component và mã active menu riêng.

## Upsale

Các trang hồ sơ khách hàng, tác nghiệp sale và đơn hàng tiếp tục sử dụng dữ liệu `orders`, `order_items`, `lead_ingestions` và service hiện có. Dòng upsale được đánh dấu trong bảng; dialog lịch sử tác nghiệp, tin nhắn nội bộ, lịch sử mua hàng và link hồ sơ đầy đủ dùng endpoint thật. Import và nhập data thủ công vẫn chạy qua `ManualLeadController` nên giữ nguyên idempotency, hold window, review ngoại lệ và khóa đơn.

## Tạo lại fragment

```bash
python scripts/build_pushsale_templates.py /path/to/template public/pushsale-templates
npm run build
```

## Triển khai

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```
