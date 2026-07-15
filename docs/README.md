# ERM SaleOps — Tài liệu dự án

> **ERM SaleOps** (Enterprise Resource Management — Sales Operations) là hệ thống vận hành bán hàng nội bộ. Tham chiếu nghiệp vụ từ pushsale.vn nhưng **thương hiệu và tên hiển thị riêng**.

## Mục lục

| Tài liệu | Đối tượng | Nội dung |
|----------|-----------|----------|
| [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md) | Quản lý, Marketing, Telesale, Kho, Kế toán, Chia số | Luồng nghiệp vụ end-to-end, vai trò, thao tác hàng ngày, route nhanh |
| [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md) | Developer, architect | Stack, kiến trúc layered, domain model, báo cáo, realtime, trạng thái triển khai |
| [API_AND_ROUTES.md](./API_AND_ROUTES.md) | Developer, DevOps, tích hợp | REST API v1, webhook, Inertia pages, bảng route thực tế từ `web.php` |
| [CHANGELOG.md](./CHANGELOG.md) | Toàn team | Timeline milestone và spec đã hoàn thành / đang làm |
| [LANDING_CONNECTION_FLOW_V13.md](./LANDING_CONNECTION_FLOW_V13.md) | Marketing, Telesale, Developer | Luồng Kết nối Landing thay campaign, mapping sản phẩm, upsale và tính giá backend |
| [CONTEXT_HANDOFF_V13.md](./CONTEXT_HANDOFF_V13.md) | Developer | Điểm bàn giao V13, file thay đổi, kiểm thử và bước triển khai |

## Khởi động nhanh

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

**Tài khoản demo** (mật khẩu `password`):

> Quy ước: `{vai trò}@saleops.local` là **Trưởng bộ phận**; nhân viên dùng hậu tố số (`sale01@`, `mkt01@`, `wh01@`). Danh sách đầy đủ hiển thị ngay tại trang đăng nhập (nút "Xem danh sách tài khoản demo").

| Role | Email | Vị trí |
|------|-------|--------|
| Super Admin | `superadmin@saleops.local` | Chủ hệ thống — quản trị nền tảng + full nội bộ |
| Admin | `admin@saleops.local` | Quản trị công ty |
| Telesale | `sales@saleops.local` | Trưởng bộ phận Telesale |
| Telesale | `leader.sale.a@saleops.local` / `leader.sale.b@saleops.local` | Trưởng nhóm Sale A / B |
| Telesale | `sale01@` … `sale05@saleops.local` | Nhân viên Telesale |
| Marketing | `marketing@saleops.local` | Trưởng bộ phận Marketing |
| Marketing | `leader.marketing.a@saleops.local` / `leader.marketing.b@saleops.local` | Trưởng nhóm Marketing A / B |
| Marketing | `mkt01@` … `mkt06@saleops.local` | Nhân viên Marketing |
| Kho | `warehouse@saleops.local` | Trưởng kho — ký duyệt nhập/xuất |
| Kho | `wh01@` / `wh02@saleops.local` | Nhân viên kho |
| Chia số | `allocator@saleops.local` | Trưởng bộ phận Chia số |
| Kế toán | `accounting@saleops.local` | Trưởng bộ phận Kế toán |

> Chi tiết cơ cấu & quyền của từng tài khoản: docblock `database/seeders/AccountSeeder.php`.

## Làm mới dữ liệu demo (kể cả trên production / AWS)

```bash
php artisan migrate --force && php artisan db:seed --force
```

`db:seed` luôn **xóa sạch dữ liệu nghiệp vụ + tài khoản cũ rồi seed lại từ đầu** (`DemoResetSeeder`), giữ nguyên cấu hình kết nối nền tảng & hãng vận chuyển. Dữ liệu sinh ra deterministic — seed bao nhiêu lần cũng cho cùng một bộ số liệu đồng bộ: tồn kho khớp lịch sử nhập xuất, lead khớp đơn hàng, doanh số khớp báo cáo.

## Liên kết chéo

- Luồng nghiệp vụ chi tiết → [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md)
- Checklist field báo cáo / bảng mới → [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md) § Data model
- Cấu hình webhook Facebook/TikTok/Ladipage → [API_AND_ROUTES.md](./API_AND_ROUTES.md) § Tích hợp
- Theme: `brand`, `ocean`, `sunset`, `violet` — `config/saleops.php`, localStorage `saleops-appearance`
