# ERM Pushsale

Dự án Laravel/Inertia mô phỏng giao diện Pushsale theo business ERM: quản trị đơn vị, marketing/landing, telesale, kho, kế toán và báo cáo.

## Nguyên tắc làm tiếp

- Dùng chung shell `AppLayout` + `AppSidebar` + `PushsalePageShell`/`PushsalePageHeader` cho mọi trang admin.
- CSS Pushsale runtime chỉ đăng ký qua `resources/js/lib/pushsaleStyleRegistry.js`.
- CSS sửa cuối cùng nằm trong `resources/css/pushsale-adminlte-canonical-contract.css`; không đẻ thêm file CSS vặt nếu chỉ để override global.
- Page CSS phải scope bằng class trang, ví dụ `.psm-page`, `.pslc-page`, `.ps-warehouses-page`.
- Không xóa `public/vendor/font-awesome` và `public/vendor/adminlte2` khi thay source, vì legacy icon/AdminLTE còn dùng trực tiếp.

## Lệnh deploy/test tối thiểu

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

Tài liệu chi tiết nằm trong `docs/PROJECT_CONTRACT.md`.
