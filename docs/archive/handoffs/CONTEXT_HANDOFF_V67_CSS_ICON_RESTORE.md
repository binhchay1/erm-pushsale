# V67 CSS/Icon Restore

## Lý do

Sau khi dọn file, các asset legacy trong `/vendor/adminlte2` và `/vendor/font-awesome` có thể không còn tồn tại. Shell Pushsale cũ phụ thuộc Bootstrap 3, AdminLTE 2.3, FontAwesome 4, skin-blue-light và Select2. Khi FontAwesome 4 không load được, các class `fa fa-*` render thành ký tự private/fallback nên icon menu bị khác hoàn toàn.

## Thay đổi

- Thêm `resources/css/pushsale-parity-dialogs.css` làm CSS authority cuối cùng cho shell/menu/icon.
- `resources/js/lib/uiShellStyles.js` load vendor CSS theo thứ tự giống Pushsale cũ, có CDN fallback khi local vendor bị xóa.
- Force FontAwesome 4 CDN sau local để cứu case local CSS còn nhưng font file bị mất.
- Giữ menu contract `left-side`, `sidebar-menu ul1`, `li1/a1`, `ul2/li2/a2`, `ul3/li3/a3`.
- Chuẩn hóa root icon về FA4: cog, trophy, user, tty, tags, calculator, user-secret, dashboard, credit-card.
- Normalize các icon SVG/Lucide còn lại về size AdminLTE-ish để không bị cái to cái nhỏ trong bảng/action/header.

## Deploy

Sau khi apply patch:

```bash
pnpm install --frozen-lockfile
pnpm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.5-fpm
sudo supervisorctl restart pushsale-horizon pushsale-reverb
```

Hard refresh trình duyệt sau deploy.
