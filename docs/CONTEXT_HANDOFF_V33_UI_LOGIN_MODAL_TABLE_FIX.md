# V33 - UI polish, login pages real data, modal/table cleanup

## Scope
This patch continues from V32 and keeps Laravel Horizon intact. It focuses on the UI issues reported from the deployed `salesloop.vn` admin screens.

## Fixed

### Header / sidebar
- Sidebar menu is hidden by default on first page load.
- Hamburger button no longer inherits the AdminLTE `sidebar-toggle::before` pseudo icon.
- Sidebar open/close alignment is normalized:
  - collapsed: content starts at `left: 0`;
  - open: content starts at `left: 252px`;
  - no menu shadow;
  - third-level flyout starts flush after the sidebar instead of drifting under the header.
- Patched both source and the current built JS asset so the change works even before a new Vite build.

### Security login pages
- `AutoLoginAsAdmin` now logs a real `auth.login.success` activity once per browser session when staging auto-login is enabled.
- `1.7.1 Lịch sử đăng nhập` reads real `activity_logs` auth actions.
- `1.7.2 Quản lý đăng nhập` reads real `users` data.
- Super Admin bypasses tenant scope for login history/login management pages so these screens do not look empty when the account is a platform admin.

### Modal alignment
- Added global centering guards for Radix dialogs and legacy ERM modal layers.
- Sale/warehouse/customer dialogs get fixed viewport centering, max-height, and scroll handling.

### Table/data grid styling
- Removed heavy cell boxing from body rows.
- Kept subtle row separators only.
- Removed nested borders in money cells such as `Thành tiền / CK/VAT / Phí VC/Tổng tiền`.

## Files changed
- `resources/js/layouts/AppLayout.jsx`
- `public/build/assets/AppLayout-C6T4YjHa.js`
- `resources/css/pushsale.css`
- `public/build/assets/pushsale-VZglJWi2.css`
- `app/Http/Middleware/AutoLoginAsAdmin.php`
- `app/Services/Pushsale/PushsalePageService.php`
- `docs/CONTEXT_HANDOFF_V33_UI_LOGIN_MODAL_TABLE_FIX.md`

## Verification run in sandbox
```bash
php -l app/Http/Middleware/AutoLoginAsAdmin.php
php -l app/Services/Pushsale/PushsalePageService.php
php -l app/Providers/HorizonServiceProvider.php
node --check public/build/assets/AppLayout-C6T4YjHa.js
```

## Server deploy notes
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

To force a fresh login history row on staging auto-login, open the site in incognito or clear the session cookie.
