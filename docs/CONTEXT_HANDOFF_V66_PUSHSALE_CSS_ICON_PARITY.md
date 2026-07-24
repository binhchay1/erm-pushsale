# V66 Pushsale CSS/Icon Parity Cleanup

## Mục tiêu

Bản này xử lý lại phần CSS bị lệch sau khi dọn file và sau các patch chồng nhau:

- Menu mặc định ẩn; bấm hamburger mở menu dạng overlay, không co/đẩy nội dung.
- Trả lại contract HTML/CSS menu giống Pushsale cũ: `left-side`, `sidebar-menu ul1`, `li1/a1`, `ul2/li2/a2`, `ul3/li3/a3`.
- Dùng FontAwesome 4 làm hệ icon duy nhất cho shell/menu/action; không để lucide/bi/material lẫn vào menu chính.
- Cố định kích thước icon menu root, icon `+/-`, icon `angle-right`, header info/language/notification/action để không còn cái to/cái nhỏ.
- Header giữ tên user đang đăng nhập, bỏ điểm bảo mật, icon hướng dẫn nằm cạnh ngôn ngữ.
- Page title/filter/action dùng một chrome chung; bỏ padding/box-shadow/border lạ ở đầu trang.
- Dialog/Radix modal căn giữa, không tràn viewport, z-index cao hơn sidebar/flyout.

## File chính

- `resources/css/pushsale-parity-base.css`: final CSS authority, được load sau `pushsale.css`.
- `resources/js/lib/uiShellStyles.js`: import thêm `pushsale-parity-base.css` sau bundle Pushsale cũ.

## Ghi chú kỹ thuật

Không xóa các block cũ trong `pushsale.css` ở bản này để tránh làm mất CSS theo từng trang đang có dữ liệu thật. Thay vào đó V66 tách một file authority riêng, load cuối cùng. Cách này dễ rollback, dễ audit và tránh tiếp tục append block vào `pushsale.css` vốn đã có nhiều đoạn V24–V64 chồng nhau.

## Sau deploy

Chạy đúng PNPM-only:

```bash
pnpm install --frozen-lockfile
pnpm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.5-fpm
sudo supervisorctl restart pushsale-horizon
sudo supervisorctl restart pushsale-reverb
```

Hard refresh trình duyệt hoặc xóa asset cache nếu vẫn thấy icon/menu cũ.
