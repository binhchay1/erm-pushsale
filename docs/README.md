# ERM SaleOps — Tài liệu dự án

> **ERM SaleOps** (Enterprise Resource Management — Sales Operations) là hệ thống vận hành bán hàng nội bộ. Tham chiếu nghiệp vụ từ pushsale.vn nhưng **thương hiệu và tên hiển thị riêng**.

Agent / model mới: đọc **[`AGENTS.md`](../AGENTS.md)** trước, rồi contract sống bên dưới. Không tạo `CONTEXT_HANDOFF_V*` mới.

## Nguồn sự thật (living)

| Tài liệu | Đối tượng | Nội dung |
|----------|-----------|----------|
| [../AGENTS.md](../AGENTS.md) | Agent, developer | Naming theo menu, CSS registry, page shell, SOLID, cấm spam docs |
| [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md) | Developer | UI shell, CSS cascade, landing, sidebar, phân bổ data, deploy |
| [PUSHSALE_ROUTE_CONTRACT.md](./PUSHSALE_ROUTE_CONTRACT.md) | Developer | Menu code, URL canonical, report route map |
| [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md) | Ops / quản lý | Luồng nghiệp vụ end-to-end |
| [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md) | Architect | Stack, domain, báo cáo, realtime |
| [API_AND_ROUTES.md](./API_AND_ROUTES.md) | Developer, DevOps | REST API, webhook, route map |
| [DEPLOY_PERMISSION_CONTRACT.md](./DEPLOY_PERMISSION_CONTRACT.md) | DevOps | Quyền `public/build` khi deploy |
| [CHANGELOG.md](./CHANGELOG.md) | Team | Milestone timeline |
| [PROJECT_VERSION_LOG.md](./PROJECT_VERSION_LOG.md) | Developer | Index phiên bản cũ (không đọc hết handoff) |

## Domain keepers (khi đụng feature đó)

| Tài liệu | Khi nào đọc |
|----------|-------------|
| [PANCAKE_INTEGRATION.md](./PANCAKE_INTEGRATION.md) / [PANCAKE_ASSIGNMENT_FLOW.md](./PANCAKE_ASSIGNMENT_FLOW.md) | Chat / gán hội thoại Pancake |
| [CUSTOMER_CHAT_REALTIME_SECURITY.md](./CUSTOMER_CHAT_REALTIME_SECURITY.md) | Realtime chat |
| [HORIZON_REDIS_OPERATIONS.md](./HORIZON_REDIS_OPERATIONS.md) | Queue / Horizon |
| [LANDING_CONNECTION_BACKEND_RESET_V122.md](./LANDING_CONNECTION_BACKEND_RESET_V122.md) | Landing connection backend (chi tiết; tóm tắt trong PROJECT_CONTRACT) |

## Archive (không phải contract hiện tại)

- Handoff / UI versioned: [`archive/handoffs/`](./archive/handoffs/)
- CSS orphan: [`../resources/css/_archive/`](../resources/css/_archive/)
- Release notes lịch sử: [`context-history/`](./context-history/) (nếu có)

## Khởi động nhanh

```bash
composer install && pnpm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

**Tài khoản demo** (mật khẩu `password`):

> Quy ước: `{vai trò}@saleops.local` là **Trưởng bộ phận**; nhân viên dùng hậu tố số (`sale01@`, `mkt01@`, `wh01@`). Danh sách đầy đủ hiển thị tại trang đăng nhập.

| Role | Email |
|------|-------|
| Super Admin | `superadmin@saleops.local` |
| Admin | `admin@saleops.local` |
| Telesale | `sales@saleops.local`, `sale01@`… |
| Marketing | `marketing@saleops.local`, `mkt01@`… |
| Kho | `warehouse@saleops.local`, `wh01@`… |
| Chia số | `allocator@saleops.local` |
| Kế toán | `accounting@saleops.local` |

Chi tiết: docblock `database/seeders/AccountSeeder.php`.

## Làm mới dữ liệu demo

```bash
php artisan migrate --force && php artisan db:seed --force
```

`db:seed` reset nghiệp vụ + tài khoản rồi seed lại (`DemoResetSeeder`).
