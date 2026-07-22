# V85 - Page header/menu contract

## Mục tiêu

Chuẩn hoá lại phần chrome của giao diện Pushsale/ERM để các trang trong menu không còn tự dựng title, filter, action và menu theo nhiều cấu trúc khác nhau.

Các lỗi được xử lý trong nhánh này:

- Title trang bị lệch giữa hoặc tụt vào dòng filter tuỳ từng page.
- Page có filter và page không có filter không dùng cùng một contract.
- Login history, user list và marketing dashboard có header khác nhau.
- Sidebar/menu bị ảnh hưởng bởi CSS của page content.
- Sidebar mở/đóng làm content co/dịch thay vì overlay lên nội dung.
- Menu cấp 3/flyout không giống mẫu Pushsale.

## Contract mới

`PushsalePageShell` là contract chung cho mọi trang React/Inertia:

```jsx
<PushsalePageShell
    title="Tên trang"
    primaryFilters={...}
    advancedFilters={...}
    actions={...}
>
    <PageBody />
</PushsalePageShell>
```

Cấu trúc cố định:

1. Main row: title bên trái, filter chính ở giữa, action/search/help/gear bên phải.
2. Advanced row: filter phụ, chỉ hiện khi có `advancedFilters` và được điều khiển bằng nút mũi tên.
3. Body: table/chart/form nằm dưới header, không tự thêm padding đầu trang.

Các adapter cũ vẫn đi qua shell:

- `PageHeader` dùng `PushsalePageShell` nhưng ẩn body.
- `PushsalePageFrame` dùng `PushsalePageShell` và bọc cả body.

## CSS final contract

File mới:

```text
resources/css/pushsale-v85-page-shell-menu-contract.css
```

File này được load cuối qua registry:

```text
resources/js/lib/pushsaleStyleRegistry.js
```

Nguyên tắc CSS:

- Sidebar/topbar chỉ scope dưới `.pushsale-wrapper`, `.pushsale-main-header`, `.pushsale-main-sidebar`.
- Page header/body chỉ scope dưới `.ps-page-shell` hoặc `.ps-page-viewport`.
- Không thêm selector generic kiểu `table`, `input`, `button`, `select` toàn app.
- Legacy `m-header-wrap`, `content-header`, `psm-topbar`, `ps-login-toolbar` được normalize như fallback để các page chưa migrate JSX vẫn cùng layout.

## Page đã migrate trực tiếp

- `resources/js/pages/Pushsale/Pages/Page_1_7_1.jsx`: Lịch sử đăng nhập.
- `resources/js/pages/Admin/Marketing/Dashboard.jsx`: Marketing dashboard.

Các trang dùng `PageHeader` / `PushsalePageFrame` tự nhận contract mới từ shared component.

## Kiểm tra đã chạy

```bash
node ./scripts/audit-pushsale-contract.mjs
```

Kết quả trong sandbox: `33 pass, 12 warn, 0 fail`.

Không chạy được `pnpm build` trong sandbox vì môi trường không có `pnpm` và không có `node_modules`.

## Ghi chú triển khai

Sau khi copy code lên server hoặc push git, chạy:

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Sau deploy cần test tối thiểu các URL:

- `/admin/users`
- `/admin/security/login-history`
- `/admin/marketing/dashboard`
- các page còn dùng legacy `m-header-wrap` như sản phẩm, kho, khách hàng 360, kết nối landing.
