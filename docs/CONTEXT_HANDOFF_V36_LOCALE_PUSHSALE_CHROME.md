# V36 — Locale + Pushsale chrome consistency

## Mục tiêu
- Sửa đổi ngôn ngữ không hoạt động ổn định trên staging.
- Đồng bộ lại thanh title/filter theo kiểu Pushsale gốc.
- Menu trái mặc định ẩn nhưng mở/đóng và xổ nhóm mượt hơn, có shadow nhẹ giống Pushsale.
- Không thay đổi Horizon.

## Thay đổi chính

### Locale / đa ngôn ngữ
- `routes/web.php`: `/locale` nhận cả `GET` và `POST`.
- `LocaleController`: lưu locale vào session, user preferences và cookie `locale`, sau đó redirect an toàn về URL hiện tại.
- `SetLocale`: đọc locale theo thứ tự query/body -> session -> cookie -> user preferences -> app default.
- `I18nProvider`: khi chọn ngôn ngữ sẽ set local/session storage + cookie + `documentElement.lang`, rồi hard reload qua `/locale?locale=...&redirect=...` để mọi dữ liệu Inertia/server-side label đồng bộ ngay.
- `LocaleSync`: cập nhật `document.documentElement.lang` sau mỗi Inertia visit.
- `NavigationService`: localize menu server-side khi locale khác `vi`.
- Thêm `lang/en/pushsale_navigation.php` cho cây menu Pushsale.

### Giao diện shell Pushsale
- Thêm CSS V36 ở cuối `resources/css/pushsale.css` và active built CSS `public/build/assets/pushsale-VZglJWi2.css`.
- Content luôn full width; menu trái là overlay, không đẩy layout.
- Header vẫn giữ tên user khi menu ẩn.
- Sidebar mở/đóng bằng transform mượt, submenu xổ bằng max-height transition.
- Bỏ pseudo `::before/::after` của hamburger.
- Title/filter các trang report dùng grid: title cố định bên trái, filter/action bên phải.
- AdminLTE page header/filter bỏ pseudo item, giảm cảm giác trắng rỗng có viền dưới.
- Border bảng KPI/report nhẹ lại về 1px.

## Files touched
- `routes/web.php`
- `app/Http/Controllers/LocaleController.php`
- `app/Http/Middleware/SetLocale.php`
- `app/Services/NavigationService.php`
- `lang/en/pushsale_navigation.php`
- `resources/js/providers/I18nProvider.jsx`
- `resources/js/components/layout/LocaleSync.jsx`
- `resources/css/pushsale.css`
- `public/build/assets/I18nProvider-DbVuWjts.js`
- `public/build/assets/pushsale-VZglJWi2.css`

## Deploy
```bash
cd /var/www/erm-pushsale
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

## Check nhanh
- `/admin/users`
- `/admin/company/profile`
- `/sales/reports/sale-4`
- `/sales/reports/sale-3`
- `/sales/customers`
- Đổi VI/EN trên header, menu phải đổi theo sau reload.
