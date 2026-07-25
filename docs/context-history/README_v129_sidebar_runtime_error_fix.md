# v129 Sidebar runtime error fix

## Mục tiêu

Fix lỗi runtime `childActive is not defined` khi mở menu/sidebar trên các trang AdminLTE/Inertia.

## Nguyên nhân

Trong `ThirdLevelFlyout` của `resources/js/components/layout/AppSidebar.jsx`, JSX của menu cấp 3 vô tình dùng biến `childActive`, `hoverSecondKey`, `key` vốn chỉ tồn tại trong scope render menu cấp 2. JavaScript không bắt lỗi ở build vì đây là free variable runtime, nên khi mở menu mới văng lỗi ứng dụng.

## Sửa đổi

- Thay đoạn render title trong flyout cấp 3 về scope-local, chỉ dùng `active` đã được khai báo trong callback map.
- Không đổi contract CSS/menu khác ở bản này để tránh tạo thêm override không cần thiết.

## Test cần chạy

```bash
pnpm build
php artisan optimize:clear
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

## Test tay

- Vào `/admin/products/import`.
- Click hamburger mở menu.
- Hover/click các menu có submenu cấp 3 và menu cấp 2 không có submenu cấp 3.
- Không được còn lỗi `childActive is not defined`.
