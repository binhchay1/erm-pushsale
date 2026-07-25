# ERM Pushsale Project Contract

## 1. Kiến trúc giao diện

Tất cả trang admin phải đi qua `AppLayout`. Trang nghiệp vụ dùng một trong hai shell:

- `resources/js/components/layout/PushsalePageShell.jsx`: trang có title + filter chính + filter nâng cao + body.
- `resources/js/components/pushsale/PushsalePageHeader.jsx`: trang legacy cần header giống Pushsale HTML mẫu.

Không viết mỗi trang một bộ header/filter riêng nữa. Nếu thiếu use case, mở rộng component shell, không copy layout.

## 2. CSS contract

Thứ tự CSS runtime nằm ở `resources/js/lib/pushsaleStyleRegistry.js`.

- Vendor legacy: `/public/vendor/adminlte2`, `/public/vendor/font-awesome`.
- CSS base/page scoped: `resources/css/pushsale-*.css`.
- CSS cuối cùng: `resources/css/pushsale-adminlte-canonical-contract.css`.

Quy tắc: selector global sidebar/header chỉ được đặt ở file canonical cuối cùng. Các trang riêng chỉ được scope bằng class trang.

## 3. Luồng Landing Connection mới

Menu 2.4.1 chỉ tạo nguồn landing:

- loại kết nối,
- cấu hình chia số,
- tên nguồn,
- URL landing,
- kênh quảng cáo,
- upsale URL,
- sale ưu tiên,
- nhập thủ công.

Không chọn sản phẩm ở bước tạo. Bước duyệt ở `/admin/marketing/landing-approvals` mới gắn sản phẩm/gói và ngân sách rồi sync sang bảng legacy `marketing_sources` để báo cáo/lead flow cũ vẫn đọc được.

## 4. Backend rules

- Controller chỉ validate request và gọi service.
- Service chịu trách nhiệm transaction/business flow.
- Model không chứa logic validate business phức tạp.
- Runtime repair chỉ vá schema không phá dữ liệu.
- Không để route view trả 500; lỗi business phải quay lại form với validation/flash.

## 5. Test tối thiểu sau mỗi lần sửa

```bash
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
php artisan erm:test-all --landing-flow --flow --json
```

Route smoke chỉ fail khi có lỗi nặng như 500/exception/PHP error. 403/404 được tính warning để log không nhiễu.

## Deploy permission contract

- `public/build` is generated output. It must stay writable by the deploy user before `pnpm build`.
- Use `deploy/fix-build-permissions.sh` before Vite build in every deploy path.
- Do not run a manual root-owned build without restoring owner to `deploy:www-data`.
