# ERM Pushsale V23 — UI Consolidation Contract

## Mục tiêu

V23 khóa lại lớp giao diện nội bộ để cả những module riêng của ERM cũng chạy chung ngôn ngữ giao diện Pushsale: header, sidebar, font Arial, button, input, table, card, modal và tab.

Những màn trước đây còn dùng kiểu card/rounded/Tailwind riêng được đưa về cùng contract:

- Giám sát hệ thống.
- Chi tiết payload giám sát.
- Kết nối nền tảng/webhook/Pancake extension.
- Modal tin nhắn nội bộ và chat Pancake của hồ sơ khách hàng.
- Đơn giao vận và đối soát COD/giao vận.
- Các trang cấu hình/profile/settings còn dùng component `Card`, `Button`, `Input`, `StatusBadge` chung.

## CSS contract

File mới:

```text
resources/css/pushsale-system-components.css
```

File này được import cuối trong:

```text
resources/css/pushsale.css
```

Nguyên tắc:

1. Không tác động `public.css`, `public-shell.css`, trang login hoặc website bên ngoài.
2. Chỉ áp dụng trong `body.pushsale-app-body`.
3. Các component riêng như `Card`, `Button`, `Input`, `Dialog`, `StatusBadge` được ép về Pushsale-style.
4. Bảng trong custom module dùng header xanh `#3782dc`, border `#2f72c4`, zebra row giống Pushsale.
5. Modal custom giữ chung shell: header xanh, body cuộn, footer cố định, không border-radius lớn.
6. Các tab custom chuyển về tab strip của Pushsale.

## Các class wrapper mới

```text
ps-feature-page
ps-system-monitor-page
ps-system-monitor-detail
ps-integrations-page
ps-shipping-orders-page
ps-shipping-reconciliation-page
ps-customer-chat-modal
```

Các wrapper này không thay đổi nghiệp vụ/backend, chỉ gom UI riêng về cùng hệ giao diện.

## Lưu ý deploy

Nếu không build lại frontend thì cần giữ `public/build` đã được cập nhật trong package. Khi deploy chuẩn vẫn nên chạy:

```bash
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```
