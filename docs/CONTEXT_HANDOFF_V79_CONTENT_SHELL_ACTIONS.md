# V79 - Unified content shell, pagination and round floating actions

## Scope

- Chuẩn hóa content area sau top navbar thành một viewport chung `ps-page-viewport`.
- Mọi trang sẽ tiếp tục có page-specific content, nhưng phần bọc ngoài cùng được quản lý từ `AppLayout` + CSS chung.
- Sửa pagination bị lệch/rơi vào band trắng.
- Ép lại floating action buttons thành hình tròn thật, kể cả khi CSS legacy có `border-radius: 0 !important`.

## Key files

- `resources/js/layouts/AppLayout.jsx`
- `resources/css/pushsale-v79-content-shell-actions.css`
- `resources/js/lib/uiShellStyles.js`

## Notes

- Không thay đổi business/backend.
- Không thay đổi menu/icon shell.
- CSS V79 phải load cuối cùng để thắng các block legacy/parity cũ.
- Page mới nên dùng `PushsalePageFrame` hoặc đặt header/filter/content vào cùng một page root thay vì tự tạo margin/padding riêng.
