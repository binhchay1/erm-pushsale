# V26 — UI CSS Recovery & 500 Guard

## Mục tiêu

- Khôi phục toàn bộ CSS ERM/Pushsale bị mất ở V25.
- Không tiếp tục xóa CSS module cũ; giữ lại source CSS theo module để dễ đối chiếu.
- Bundle nội dung CSS nội bộ vào một entry chính `resources/css/pushsale.css` để tránh lệ thuộc vào import lồng nhau.
- Khóa lại contract sidebar/header để không còn gutter trắng 42px khi menu bị collapsed.
- Giảm rủi ro 500 ở các trang Pushsale table khi dữ liệu/migration/cache trên production chưa đồng bộ.

## CSS

Entry chính:

```text
resources/css/pushsale.css
```

File này đã được dựng lại từ các module CSS đã có ở V24:

- `pushsale-layout.css`
- `pushsale-common.css`
- `pushsale-customer-profile.css`
- `pushsale-sale-workspace.css`
- `pushsale-template-pages.css`
- `pushsale-adminlte-pages.css`
- `pushsale-ceo-report.css`
- `pushsale-marketing.css`
- `pushsale-landing-connections.css`
- `pushsale-admin-finance-dashboard.css`
- `pushsale-template-six-reports.css`
- `pushsale-warehouse.css`
- `pushsale-system-foundation.css`
- `pushsale-data-distribution.css`
- `pushsale-shipping-config-contract.css`
- `pushsale-system-layout.css`
- `pushsale-system-components.css`
- `pushsale-system-contract.css`

Các file module vẫn được giữ lại trong source. `pushsale.css` không còn `@import` để tránh lỗi production build hoặc thứ tự import bị lệch.

## Sidebar/menu

- AppLayout không đọc `localStorage.pushsale-sidebar-open` nữa.
- Menu mở mặc định khi vào app.
- Khi thu gọn, sidebar bị ẩn hẳn bằng `transform: translate3d(-100%, 0, 0)`, `visibility: hidden`, `pointer-events: none`.
- Content luôn `left: 0`, không còn bị đẩy 42px.
- Header brand 252px khi mở menu, 42px khi collapsed.

## 500 guard

`BasePushsalePageController@index` đã được bọc try/catch cho các query dữ liệu bảng Pushsale. Nếu production thiếu migration/cache hoặc một bảng nghiệp vụ chưa sẵn sàng, trang không nổ 500 trắng nữa; UI nhận `pageRuntimeError` để hiển thị banner lỗi.

Việc này không thay thế migration. Sau deploy vẫn phải chạy:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
npm ci
npm run build
```

## Static check đã chạy

- Inertia render components: không thiếu component.
- Pushsale page components: không thiếu component.
- JS alias imports: không thiếu file import.
- `resources/css/pushsale.css`: không còn `@import`, số `{}` cân bằng.
- `public/build/assets/pushsale-VZglJWi2.css`: đã patch cùng nội dung để deploy tạm không cần build ngay.
