# Pushsale template migration V3

## Phạm vi

Bản V3 sử dụng trực tiếp bộ HTML và ảnh đối chiếu trong `template(1).zip` thay vì dựng một bảng báo cáo chung rồi đổi tiêu đề. Có 69 fragment HTML đã được làm sạch và đưa vào `public/legacy-templates`; 63 mã menu có schema dữ liệu và route thực tế.

## Kiến trúc giao diện

- `resources/js/pages/Legacy/Index.jsx` tải fragment HTML theo đúng mã menu, sau đó gắn bảng dữ liệu React vào vị trí bảng gốc bằng portal.
- `resources/css/legacy-pages.css` chỉ tác động trong `.legacy-page`, tránh làm hỏng landing page và màn hình xác thực.
- `resources/css/pushsale-layout.css` giữ sidebar rộng 252px dưới dạng overlay. Nút hamburger ẩn/hiện toàn bộ sidebar, không thay đổi chiều rộng vùng nội dung.
- Menu cấp ba hiển thị flyout màu xanh bên phải sidebar như bản Pushsale gốc.
- Header giữ chuyển ngôn ngữ, thông báo, thông báo hệ thống và menu tài khoản/đăng xuất.

## Backend

- Các màn hình đã có nghiệp vụ tiếp tục đọc dữ liệu từ model/service hiện tại: nhân sự, đội nhóm, sản phẩm, nguồn marketing, lead, đơn hàng, kho, kế toán và báo cáo.
- Các phân hệ chưa có bảng dữ liệu chuyên biệt sử dụng `legacy_module_records` làm vùng lưu trữ tenant-scoped, có người tạo/cập nhật và soft delete.
- Migration: `database/migrations/2026_07_12_000000_create_legacy_module_records_table.php`.
- Cấu hình schema: `config/legacy_pages.php`.
- Cây menu và ánh xạ route: `config/pushsale_navigation.php`.
- Các trang trùng mục đích có thể dùng `template_alias` chung nhưng vẫn giữ mã menu và route riêng.

## Luồng nghiệp vụ được giữ nguyên

Trang nhập data thủ công (`2.6.2`) gửi dữ liệu vào endpoint `/admin/leads/manual`, vì vậy vẫn đi qua luồng import lead, chống trùng, tạo khách hàng/đơn và xử lý upsale hiện có. Các trang liên quan đơn hàng và khách hàng có liên kết trở lại Sale workspace, Customer profile và Warehouse operations thay vì tạo một luồng dữ liệu song song.

## Tạo lại fragment khi template thay đổi

```bash
python scripts/build_legacy_templates.py /duong-dan/template public/legacy-templates
```

## Triển khai

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`public/build` đã được đóng gói trong bản bàn giao. Khi sửa frontend, chạy lại `npm ci && npm run build`.
