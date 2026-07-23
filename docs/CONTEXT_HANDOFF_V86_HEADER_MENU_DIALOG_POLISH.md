# V86 Header/Menu/Dialog Polish

Mục tiêu: xử lý các regression còn lại sau V85 theo đúng contract Pushsale:

- Content không bị đẩy xuống bởi double `top + margin-top` dưới fixed header.
- Hamburger căn giữa trong topbar 50px, không bị lệch lên trên bởi AdminLTE `sidebar-toggle`/FontAwesome.
- Menu cấp 3/flyout không còn border-bottom/underline do CSS cũ leak vào.
- Legacy captured templates (`m-header-wrap`, `m-header`) được gắn class theo vai trò cột: title/filter/actions để header đồng bộ với `PushsalePageShell`.
- Dialog close button không hover trắng; dùng trạng thái hover xanh nhạt, icon rõ hơn.

File thay đổi chính:

- `resources/css/pushsale-v86-header-menu-dialog-polish.css`
- `resources/js/lib/pushsaleStyleRegistry.js`
- `resources/js/pages/Pushsale/BusinessPage.jsx`

Sau khi deploy cần chạy:

```bash
pnpm build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
