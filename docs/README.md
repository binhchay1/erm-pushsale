# ERM SaleOps — Tài liệu dự án

> **ERM SaleOps** (Enterprise Resource Management — Sales Operations) là hệ thống vận hành bán hàng nội bộ. Tham chiếu nghiệp vụ từ pushsale.vn nhưng **thương hiệu và tên hiển thị riêng**.

## Mục lục

| Tài liệu | Đối tượng | Nội dung |
|----------|-----------|----------|
| [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md) | Quản lý, Marketing, Telesale, Kho, Kế toán, Chia số | Luồng nghiệp vụ end-to-end, vai trò, thao tác hàng ngày, route nhanh |
| [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md) | Developer, architect | Stack, kiến trúc layered, domain model, báo cáo, realtime, trạng thái triển khai |
| [API_AND_ROUTES.md](./API_AND_ROUTES.md) | Developer, DevOps, tích hợp | REST API v1, webhook, Inertia pages, bảng route thực tế từ `web.php` |
| [CHANGELOG.md](./CHANGELOG.md) | Toàn team | Timeline milestone và spec đã hoàn thành / đang làm |

## Khởi động nhanh

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

**Tài khoản demo** (mật khẩu `password`):

| Role | Email |
|------|-------|
| Admin | `admin@saleops.local` |
| Telesale | `sales@saleops.local` |
| Marketing | `marketing@saleops.local` |
| Kho | `warehouse@saleops.local` |
| Chia số | `allocator@saleops.local` |
| Kế toán | `accounting@saleops.local` |

## Liên kết chéo

- Luồng nghiệp vụ chi tiết → [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md)
- Checklist field báo cáo / bảng mới → [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md) § Data model
- Cấu hình webhook Facebook/TikTok/Ladipage → [API_AND_ROUTES.md](./API_AND_ROUTES.md) § Tích hợp
- Theme: `brand`, `ocean`, `sunset`, `violet` — `config/saleops.php`, localStorage `saleops-appearance`
