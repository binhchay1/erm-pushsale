# Context handoff — V17 UI system and dynamic login audit

## Base tích lũy

V17 là full source tích lũy từ V16, do đó vẫn bao gồm:

- V13 Kết nối Landing và luồng main/upsale;
- V14 ngân sách, Admin Dashboard và kiểm soát dòng tiền;
- V15 reports, upsale metrics và menu theo role;
- V16 tác nghiệp kho, giao vận, webhook, COD và nhập hoàn;
- V17 hợp nhất CSS, filter, modal và lịch sử đăng nhập động.

Đây không phải patch rời.

## Các file trọng tâm V17

### CSS/build

- `resources/css/app.css`
- `resources/css/public.css`
- `resources/css/pushsale.css`
- `resources/css/pushsale-system-foundation.css`
- `resources/views/app.blade.php`
- `vite.config.js`
- `resources/js/lib/uiShellStyles.js`

### Template/runtime

- `resources/js/pages/Pushsale/BusinessPage.jsx`
- `scripts/build_pushsale_templates.py`
- `scripts/audit_pushsale_ui_v17.py`
- `public/pushsale-templates/*.html`

### Modal

- `resources/js/components/ui/pushsale-modal.jsx`
- `resources/js/components/customers/pushsale/PushsaleCustomerModals.jsx`
- `resources/js/components/customers/OrderOperationHistoryDialog.jsx`
- `resources/js/components/customers/CustomerPurchaseHistoryDialog.jsx`
- `resources/js/components/customers/CustomerMessagesDialog.jsx`

### Backend login/filter

- `app/Support/ActivityLogger.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Services/Pushsale/PushsalePageService.php`

### Test

- `tests/Feature/Pushsale/UiContractV17Test.php`
- `tests/Unit/PushsaleTemplateScopeV17Test.php`

## Dữ liệu và migration

V17 không cần migration mới. Nó sử dụng các bảng/cột đã có:

- `activity_logs`;
- `users.company_id`, `users.role`, `users.is_team_leader`;
- `products.parent_id`, `products.is_active`, `products.available_*`;
- `product_categories` và pivot `product_category_product`.

## Deploy

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

`public/build` đã được đóng gói. Không bắt buộc build lại trên production. Khi thay frontend:

```bash
npm ci --ignore-scripts
npm run build
```

## Kiểm tra sau deploy

### UI

1. Menu 1.3.1: hàng filter không còn bắt đầu từ cột thứ hai; category và quyền sử dụng lấy dữ liệu backend.
2. Cột Thao tác: không có rectangle con quanh icon.
3. Menu 1.7.1: nhân viên là user thật trong tenant; click tên phải lọc đúng `user_id`.
4. Đăng nhập/đăng xuất một tài khoản rồi reload 1.7.1: bản ghi mới phải xuất hiện.
5. Hồ sơ khách hàng: mở lịch sử tác nghiệp ở màn hình 1366×768 và 1920×1080; modal nằm giữa viewport, header/footer cố định, bảng cuộn trong body.
6. Bảng xếp hạng Sales: không còn tên/doanh số capture; số liệu khớp bảng chi tiết.
7. Chuyển từ login/public sang ERM và ngược lại: không được kế thừa font/reset của shell kia.

### Lệnh

```bash
python scripts/audit_pushsale_ui_v17.py
php artisan test --filter=UiContractV17Test
php artisan test --filter=PushsaleTemplateScopeV17Test
```

## Lưu ý

Môi trường đóng gói không có `vendor/autoload.php`, vì vậy PHPUnit chưa thể thực thi tại đây. Test đã được thêm vào source để chạy sau `composer install`.
