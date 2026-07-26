# V64 Menu/Icon Pushsale Parity

## Mục tiêu

Khôi phục lại hệ menu/icon giống HTML Pushsale gốc sau khi cleanup file làm lệch CSS/icon.

## Phần đã sửa

- `resources/js/components/layout/AppSidebar.jsx`
  - Render lại markup menu gần với Pushsale gốc: `left-side`, `sidebar-menu ul1`, `li1/a1`, `ul2`, `li2/a2`, `ul3/li3/a3`.
  - Chuẩn hóa root icon theo Pushsale gốc:
    - 1 `fa-cog`
    - 2 `fa-trophy`
    - 3 `fa-user`
    - 4 `fa-tty`
    - 5 `fa-tags`
    - 6 `fa-calculator`
    - 7 `fa-user-secret`
    - 8 `fa-dashboard`
    - 9 `fa-credit-card`
  - Tách icon chính, text, icon `plus/minus`, icon `angle-right` thành vùng riêng để không còn cái to/cái nhỏ.
  - Level 3 flyout render bằng `ul3` kiểu Pushsale thay vì div custom.

- `resources/css/pushsale.css`
- `resources/css/pushsale-layout.css`
- `config/horizon.php`
  - Gộp unique danh sách queue `supervisor-reports-batch` để không còn `reports-history` bị lặp khi `QUEUE_REPORTS` fallback trùng `QUEUE_REPORTS_HISTORY`.
  - Thêm block V64 cuối file để override các block V24-V63 cũ đang conflict.
  - Ép Font Awesome dùng đúng `FontAwesome` font-family, tránh Tailwind/Geist làm icon thành ký tự lạ.
  - Chuẩn hóa sidebar overlay: menu mặc định ẩn, click hamburger mở menu đè lên content; content không bị dịch trái/phải gây lỗi vùng khoanh đỏ.
  - Chuẩn hóa width/height/font-size/line-height của toàn bộ icon menu và icon action.
  - Chuẩn hóa root menu, submenu, third-level flyout, active/hover state theo Pushsale/AdminLTE 2.

## Sau khi deploy

```bash
npm run build
sudo -H -u deploy php artisan optimize:clear
sudo -H -u deploy php artisan config:cache
sudo -H -u deploy php artisan route:cache
sudo -H -u deploy php artisan view:cache
sudo systemctl reload php8.5-fpm
sudo supervisorctl restart pushsale-horizon
sudo supervisorctl restart pushsale-reverb
```

## Kiểm tra nhanh

```bash
curl -sS -o /dev/null -w 'HTTP %{http_code} %{redirect_url}\n' https://salesloop.vn/admin/dashboard
```

Trong browser cần hard refresh `Ctrl+F5` sau build mới.
