# Architecture — ERM SaleOps

Stack, layers, roles. UI/CSS rules: [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md). Business flow: [OPERATIONS.md](./OPERATIONS.md).

## Stack

| Layer | Tech |
| --- | --- |
| Backend | Laravel, Sanctum, Inertia, Reverb |
| Frontend | React, Vite, Radix/shadcn, Echo |
| DB | MySQL / PostgreSQL / SQLite |
| Queue | Redis + Horizon |
| Realtime | Reverb + Echo |

```bash
composer run dev   # serve + reverb + vite
php artisan migrate --seed
```

## Layers

```text
HTTP / Inertia / API
  → Controllers (thin) + Form Requests
  → Services (Reports/*, Operations/*, Leads/*, Inventory/*)
  → Repositories / Models
  → Database
```

- Controllers: validate + call service + return Inertia/JSON.
- Business transactions (chia số, duyệt landing, xuất kho): service layer.
- Models: no heavy business validation.

## Roles (data scope)

| Role | Code | Scope |
| --- | --- | --- |
| Admin / Super | `admin` | All |
| Telesale | `sales` | Own `sale_user_id` (+ team if head) |
| Marketing | `marketing` | Own sources / campaigns |
| Warehouse | `warehouse` | Shipping / inventory ops |
| Accounting | `accounting` | COD / reconciliation |
| Allocator | `allocator` | Lead distribution |

Org tree: Trưởng bộ phận → team leader → member (`OrgStructureService`).

## API surface (overview)

Base: `{APP_URL}/api/v1`.

- Auth Sanctum: `POST /auth/token`, `GET /auth/me`, `DELETE /auth/token`
- Landing public: form/webhook submit (per-source token)
- Shipping webhooks when carrier integration enabled
- Internal ops APIs under `/admin/...` via session (Inertia)

Response shape: `{ success, message?, data }` or Laravel validation 422.

Full route list: `php artisan route:list`. Canonical menus: `config/pushsale_navigation.php` + `routes/admin/*`.

## Domains

| Domain | Menu | Key paths |
| --- | --- | --- |
| Company / HR | 1.x | `/admin/hr/...`, `/admin/company/...` |
| Marketing | 2.x | Landing connections, approvals, reports |
| Customers | 3.x / 4.2 | Customer profile |
| Sales ops | 4.1, 4.5–4.6 | Sale workspace, leader reports |
| Warehouse | 5.x | Operations, vouchers, inventory |
| Accounting | 6.x | Ops, expenses, reconciliation |
| CEO / reports | 7–8.x | Dashboards + extra reports |
