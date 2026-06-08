# ERM SaleOps — API, Routes & Tích hợp

> Base API: `{APP_URL}/api/v1`. Luồng nghiệp vụ: [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md). Kiến trúc: [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md).

---

## 1. Quy ước response API

**Thành công:**

```json
{ "success": true, "message": "optional", "data": {} }
```

**Lỗi validation (422):**

```json
{ "message": "...", "errors": { "field": ["..."] } }
```

**Lỗi API:**

```json
{ "success": false, "message": "..." }
```

**Phân trang:** `data`, `meta`, `links` (Laravel pagination). Frontend map vào `IPaginationResponse`, `IErrorPaginationResponse`, `IPaginatedResponse` (`backend.d.ts`).

---

## 2. REST API v1 — Đã triển khai (`routes/api.php`)

### Auth (Sanctum)

| Method | Path | Middleware | Mô tả |
|--------|------|------------|-------|
| POST | `/auth/token` | throttle | Login → Bearer token |
| GET | `/auth/me` | auth:sanctum | User hiện tại |
| DELETE | `/auth/token` | auth:sanctum | Thu hồi token |

**POST /auth/token** body: `{ "email", "password", "device_name" }` → `201` với `access_token`, `user.role`.

### Dashboard

| Method | Path | Middleware | Mô tả |
|--------|------|------------|-------|
| GET | `/dashboard/summary` | auth:sanctum | KPI theo role (sales scoped) |

### Orders & Leads

| Method | Path | Middleware | Mô tả |
|--------|------|------------|-------|
| GET | `/orders` | auth:sanctum | Danh sách (sales: own only) |
| GET | `/orders/{id}` | auth:sanctum | Chi tiết |
| GET | `/leads` | auth:sanctum | Log ingestion |
| GET | `/leads/{id}` | auth:sanctum | Chi tiết |
| POST | `/leads` | auth:sanctum | Tạo lead landing (Bearer) |

### Integrations (Admin)

| Method | Path | Middleware | Mô tả |
|--------|------|------------|-------|
| GET | `/integrations` | auth:sanctum, role:admin | Danh sách |
| GET | `/integrations/{platform}` | auth:sanctum, role:admin | Chi tiết |
| PUT | `/integrations/{platform}` | auth:sanctum, role:admin | Cập nhật |

`platform`: `facebook`, `tiktok`, `zalo`, `landing`, `google`

### Webhooks (không Bearer)

| Method | Path | Mô tả |
|--------|------|-------|
| GET/POST | `/webhooks/{platform}` | Lead từ FB/TikTok/Zalo/Landing/Google/Shopee/Lazada |
| POST | `/landing/{token}/receive` | Webhook theo campaign token (16–64 ký tự) |
| POST | `/shipping/webhooks/{provider}` | Trạng thái vận chuyển |

**Platform lead:** `facebook|tiktok|zalo|landing|ladipage|google|shopee|lazada`  
**Provider ship:** `viettel_post|ghn|ghtk|jnt|spx`

### API Resources

Namespace `App\Http\Resources\V1\`: `UserResource`, `OrderResource`, `LeadIngestionResource`, `IntegrationResource`.

### Ví dụ curl

```bash
curl -X POST http://localhost:8000/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@saleops.local","password":"password","device_name":"cli"}'

curl http://localhost:8000/api/v1/integrations \
  -H "Authorization: Bearer YOUR_TOKEN"

curl -X POST http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","phone":"0909123456","product":"SP A"}'
```

---

## 3. REST API — Roadmap (chưa trong `api.php`)

Các endpoint dưới đây là thiết kế mục tiêu — triển khai khi cần mobile/external client:

| Nhóm | Endpoints chính |
|------|-----------------|
| Dashboard | `GET /dashboard/live` |
| Orders CRUD | `POST/PUT/PATCH/DELETE /orders`, `PATCH /orders/{id}/status` |
| Sale ops | `GET /sales/workspace`, `POST /orders/{id}/operations`, `PATCH closing-status` |
| Customers | `GET/POST/PUT /customers`, timeline |
| Leads admin | `POST /leads/{id}/retry`, `/convert` |
| Integrations | `POST /integrations/{platform}/test`, `/rotate-secret` |
| Reports | `GET /reports/ceo`, `/marketing-dashboard`, `/marketing-revenue`, `/sales-revenue`, `/export` |
| Accounting | `GET /accounting/orders`, `PATCH payment/reconciliation` |
| Warehouse | `GET /warehouse/orders`, `POST shipments/returns` |
| Inventory | `GET/POST/PATCH /products`, `/inventory`, `/inventory/adjustments` |
| Failed orders | `GET /orders/failed`, retry/resolve |
| Settings | `GET/PUT /settings/profile`, `/preferences`, `/password` |
| Notifications | `GET /notifications`, mark read |

---

## 4. Web routes — Inertia (`routes/web.php`)

### Auth & shared

| Method | Path | Name | Page / Action |
|--------|------|------|---------------|
| GET | `/` | — | Redirect theo role hoặc login |
| GET | `/login` | login | Auth/Login |
| POST | `/login` | — | Login |
| POST | `/logout` | logout | Logout |
| GET | `/org-chart` | org-chart.index | OrgChart |
| GET | `/profile` | profile.index | Profile |
| PUT | `/profile` | profile.update | |
| POST/DELETE | `/profile/avatar` | profile.avatar* | |
| GET | `/settings` | settings.index | Settings/Index |
| PUT | `/settings` | settings.update | |
| GET | `/notifications` | notifications.index | Notifications |
| POST | `/notifications/read-all` | notifications.read-all | |
| POST | `/notifications/{id}/read` | notifications.read | |

### Admin (`role:admin`, prefix `/admin`)

| Method | Path | Name | Mô tả |
|--------|------|------|-------|
| GET | `/admin/dashboard` | admin.dashboard | Dashboard CEO |
| GET | `/admin/reports/business` | admin.reports.business | Báo cáo tổng hợp |
| GET | `/admin/reports/ceo` | admin.reports.ceo | Báo cáo CEO |
| GET | `/admin/marketing/dashboard` | admin.marketing.dashboard | Dashboard MKT |
| GET | `/admin/marketing/revenue` | admin.marketing.revenue | BC doanh số MKT |
| GET | `/admin/marketing/campaign-report` | admin.marketing.campaign-report | BC chiến dịch |
| PATCH | `/admin/marketing/campaigns/{id}/budget` | admin.marketing.campaigns.budget | Cập nhật ngân sách |
| GET | `/admin/sales/revenue` | admin.sales.revenue | BC doanh số Sale |
| GET | `/admin/sales/performance` | admin.sales.performance | BC hiệu suất Sale |
| GET | `/admin/accounting` | admin.accounting | Kế toán tác nghiệp |
| GET | `/admin/warehouse/operations` | admin.warehouse.operations | Thủ kho |
| GET | `/admin/warehouse/inventory` | admin.warehouse.inventory | Tồn kho |
| POST | `/admin/warehouse/inventory/intake` | admin.warehouse.inventory.intake | Nhập kho |
| GET/POST/… | `/admin/warehouses` | admin.warehouses.* | CRUD kho |
| DELETE | `/admin/warehouse-inventories/{id}` | admin.warehouse-inventories.destroy | |
| GET | `/admin/orders/failed` | admin.orders.failed | Đơn lỗi |
| GET | `/admin/rankings` | admin.rankings | Xếp hạng |
| GET | `/admin/landing-approvals` | admin.landing-approvals.index | Duyệt landing |
| POST | `/admin/landing-approvals/{id}/approve` | admin.landing-approvals.approve | |
| GET | `/admin/integrations` | admin.integrations.index | Tích hợp nền tảng |
| PUT | `/admin/integrations/{platform}` | admin.integrations.update | |
| POST | `/admin/integrations/{platform}/test` | admin.integrations.test | |
| GET | `/admin/leads` | admin.leads.index | Nhật ký lead |
| POST | `/admin/leads/allocate` | admin.leads.allocate | Phân bổ thủ công |
| GET | `/admin/shipping-partners` | admin.shipping-partners.index | API vận chuyển |
| PUT | `/admin/shipping-partners/{provider}` | admin.shipping-partners.update | |
| POST | `/admin/shipping-partners/{provider}/test/{action}` | admin.shipping-partners.test | |
| GET | `/admin/shipping/reconciliation` | admin.shipping.reconciliation | Đối soát |
| GET | `/admin/shipping/orders` | admin.shipping.orders | Đơn VC |
| GET | `/admin/shipping/orders/{id}/detail` | admin.shipping.orders.detail | |
| POST | `/admin/shipping/orders/{id}/create-shipment` | admin.shipping.orders.create-shipment | |
| POST | `/admin/shipping/orders/{id}/sync-status` | admin.shipping.orders.sync-status | |
| POST | `/admin/shipping/orders/{id}/calculate-fee` | admin.shipping.orders.calculate-fee | |
| POST | `/admin/shipping/orders/{id}/cancel-shipment` | admin.shipping.orders.cancel-shipment | |
| GET | `/admin/shipping/orders/{id}/label` | admin.shipping.orders.label | In nhãn |
| resource | `/admin/users`, `/teams`, `/products` | admin.* | CRUD (no show) |
| DELETE | `/admin/orders/{id}` | admin.orders.destroy | |
| DELETE | `/admin/leads/{id}` | admin.leads.destroy | |
| DELETE | `/admin/failed-orders/{id}` | admin.failed-orders.destroy | |

### Sales (`role:sales`, prefix `/sales`)

| Method | Path | Name | Mô tả |
|--------|------|------|-------|
| GET | `/sales/dashboard` | sales.dashboard | Dashboard |
| GET | `/sales/rankings` | sales.rankings | Xếp hạng |
| GET | `/sales/performance` | sales.performance | BC hiệu suất |
| GET | `/sales/workspace` | sales.workspace | Tác nghiệp |
| POST | `/sales/orders/{id}/call` | sales.orders.call | Ghi nhận gọi |
| POST | `/sales/orders/{id}/operation-status` | sales.orders.operation-status | Chuyển trạng thái |
| POST | `/sales/orders/{id}/close` | sales.orders.close | Chốt đơn |
| GET | `/sales/customers` | sales.customers | Hồ sơ KH |

### Marketing (`role:marketing`, prefix `/marketing`)

| Method | Path | Name | Mô tả |
|--------|------|------|-------|
| GET | `/marketing/dashboard` | marketing.dashboard | Dashboard |
| GET | `/marketing/rankings` | marketing.rankings | Xếp hạng |
| GET | `/marketing/workspace` | marketing.workspace | Workspace MKT |
| GET | `/marketing/campaigns` | marketing.campaigns.index | Danh sách chiến dịch |
| GET | `/marketing/campaigns/create` | marketing.campaigns.create | Tạo chiến dịch |
| POST | `/marketing/campaigns` | marketing.campaigns.store | |
| GET | `/marketing/campaigns/{id}/edit` | marketing.campaigns.edit | |
| PUT | `/marketing/campaigns/{id}` | marketing.campaigns.update | |
| DELETE | `/marketing/campaigns/{id}` | marketing.campaigns.destroy | |
| GET | `/marketing/revenue` | marketing.revenue | BC doanh số |
| GET | `/marketing/campaign-report` | marketing.campaign-report | BC chiến dịch |
| PATCH | `/marketing/campaigns/{id}/budget` | marketing.campaigns.budget | |

### Warehouse (`role:warehouse`, prefix `/warehouse`)

| Method | Path | Name | Mô tả |
|--------|------|------|-------|
| GET | `/warehouse/dashboard` | warehouse.dashboard | Dashboard |
| GET | `/warehouse/workspace` | warehouse.workspace | Tác nghiệp kho |
| GET | `/warehouse/inventory` | warehouse.inventory | Tồn kho |
| POST | `/warehouse/inventory/intake` | warehouse.inventory.intake | Nhập kho |
| GET | `/warehouse/shipping/orders` | warehouse.shipping.orders | Đơn VC |
| POST/GET | `/warehouse/shipping/orders/{id}/*` | warehouse.shipping.orders.* | create/sync/fee/cancel/label |

### Accounting (`role:accounting`, prefix `/accounting`)

| Method | Path | Name | Mô tả |
|--------|------|------|-------|
| GET | `/accounting/dashboard` | accounting.dashboard | Dashboard |
| GET | `/accounting/workspace` | accounting.workspace | Tác nghiệp |

### Allocator (`role:allocator`, prefix `/allocator`)

| Method | Path | Name | Mô tả |
|--------|------|------|-------|
| GET | `/allocator/dashboard` | allocator.dashboard | Dashboard |
| GET | `/allocator/workspace` | allocator.workspace | Lead log |
| POST | `/allocator/leads/allocate` | allocator.leads.allocate | Phân bổ thủ công |

### Broadcast auth

`Broadcast::routes(['middleware' => ['web', 'auth']])` — Echo subscribe Reverb.

---

## 5. Inertia pages map

| Path | Page component |
|------|----------------|
| `/admin/dashboard` | `pages/Admin/Dashboard.jsx` |
| `/admin/reports/ceo` | `pages/Admin/Reports/Ceo.jsx` |
| `/admin/marketing/dashboard` | `pages/Admin/Marketing/Dashboard.jsx` |
| `/admin/marketing/revenue` | `pages/Admin/Marketing/Revenue.jsx` |
| `/admin/marketing/campaign-report` | `pages/Admin/Marketing/CampaignReport.jsx` |
| `/admin/sales/revenue` | `pages/Admin/Sales/Revenue.jsx` |
| `/admin/sales/performance` | `pages/Admin/Sales/PerformanceReport.jsx` |
| `/admin/accounting` | `pages/Admin/Accounting/Index.jsx` |
| `/admin/warehouse/operations` | `pages/Admin/Warehouse/Operations.jsx` |
| `/admin/warehouse/inventory` | `pages/Admin/Warehouse/Inventory.jsx` |
| `/admin/orders/failed` | `pages/Admin/Orders/Failed.jsx` |
| `/sales/workspace` | `pages/Sales/Workspace.jsx` |
| `/sales/customers` | `pages/Sales/Customers.jsx` |
| `/sales/dashboard` | `pages/Sales/Dashboard.jsx` |
| `/marketing/campaigns` | `pages/Marketing/Campaigns/*` |
| `/settings` | `pages/Settings/Index.jsx` |
| `/login` | `pages/Auth/Login.jsx` |

Layout: `AdminLayout` (`/admin/*`), `SalesLayout` (`/sales/*`), role layouts tương ứng cho marketing/warehouse/accounting/allocator.

---

## 6. Middleware

| Middleware | Dùng cho |
|------------|----------|
| `auth` | Inertia web |
| `auth:sanctum` | API Bearer |
| `role:admin\|sales\|…` | Route groups theo role |
| `guest` | Login |
| `throttle:api` | API public |
| `webhook.signature` | Xác thực webhook (nếu bật) |

---

## 7. Tích hợp nền tảng — Lead

### Kiến trúc

```text
Nền tảng → POST/GET /api/v1/webhooks/{platform}
  → Driver chuẩn hóa payload
  → LeadIngestionService → Order
  → Event LeadIngested (Reverb)
```

### URL webhook công khai (HTTPS)

| Nền tảng | URL |
|----------|-----|
| Facebook | `{APP_URL}/api/v1/webhooks/facebook` |
| TikTok | `{APP_URL}/api/v1/webhooks/tiktok` |
| Zalo | `{APP_URL}/api/v1/webhooks/zalo` |
| Landing | `{APP_URL}/api/v1/webhooks/landing` hoặc `/ladipage` |
| Google | `{APP_URL}/api/v1/webhooks/google` |
| Shopee / Lazada | `{APP_URL}/api/v1/webhooks/shopee` / `lazada` |
| Campaign token | `{APP_URL}/api/v1/landing/{token}/receive` |

### Facebook Lead Ads

1. Meta Developers → App Business → Webhooks + Marketing API.
2. Subscribe field **`leadgen`**, callback URL + Verify Token.
3. `.env`: `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, `FACEBOOK_PAGE_ACCESS_TOKEN`, `FACEBOOK_VERIFY_TOKEN`.
4. POST ký bằng `X-Hub-Signature-256` = HMAC body + App Secret.

### TikTok / Zalo / Google

Cấu hình webhook trỏ URL tương ứng. Xác thực: `X-SaleOps-Signature` (HMAC + `INTEGRATION_WEBHOOK_SECRET`) hoặc `X-Api-Key` từ `integration_connections.webhook_secret`.

### Landing / Ladipage

**A. Webhook (không login):**  
`POST /api/v1/webhooks/landing` — Header `X-Api-Key: {LANDING_API_KEY}` hoặc query `?api_key=...`

**B. Bearer:** `POST /api/v1/leads`

Body ví dụ:

```json
{
  "name": "Nguyễn Văn A",
  "phone": "0901234567",
  "product": "Kem dưỡng X",
  "utm_source": "landing",
  "utm_campaign": "summer-2026"
}
```

`LandingFormDriver` map heuristic: `phone`/`dien_thoai`/`sdt`, `name`/`ho_ten`, `product`/`san_pham`, nested `fields[]`.

### Admin UI

- Lead platforms: **`/admin/integrations`**
- API vận chuyển: **`/admin/shipping-partners`**

**PUT /api/v1/integrations/{platform}** (admin token):

```json
{
  "is_enabled": true,
  "verify_token": "saleops_fb_verify_2026",
  "webhook_secret": "optional",
  "credentials": { "page_access_token": "EAAx..." }
}
```

### Biến môi trường

```env
INTEGRATION_WEBHOOK_SECRET=change-me-long-random
LANDING_API_KEY=your-random-secret-key
LEAD_ROUTING_STRATEGY=round_robin
LEAD_DUPLICATE_WINDOW_DAYS=30
FACEBOOK_* / TIKTOK_* / ZALO_* / GOOGLE_LEADS_WEBHOOK_KEY
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

---

## 8. Tích hợp vận chuyển

### Webhook trạng thái

`POST /api/v1/shipping/webhooks/{provider}`  
Provider: `viettel_post`, `ghn`, `ghtk`, `jnt`, `spx`

### Admin actions (web)

Tạo vận đơn, sync status, tính phí, hủy, in label — qua `/admin/shipping/orders/{order}/*` (warehouse role mirror tại `/warehouse/shipping/orders/*`).

### Production checklist

1. `php artisan migrate`
2. Admin → Tích hợp → bật platform + credentials
3. `APP_URL` = domain HTTPS public
4. Đăng ký webhook Meta/TikTok/Ladipage/carrier
5. Bật Reverb + queue (`composer run dev` hoặc supervisor)
6. Kiểm tra `/admin/leads` + toast realtime

### Dev local (ngrok)

```bash
ngrok http 8000
# APP_URL=https://xxxx.ngrok-free.app
```

---

## 9. Realtime channels

| Channel | Events |
|---------|--------|
| `dashboard.admin` | `stats.updated`, `lead.ingested`, `order.*` |
| `dashboard.sales.{userId}` | Lead/order assigned |
| `orders.{orderId}` | Order detail updates |

Frontend: `useRealtimeDashboard` — `channel.listen('.lead.ingested', …)`.

---

## 10. Frontend conventions (tóm tắt)

- Filter → URL query → Inertia partial reload.
- Data table: server pagination, sticky header/column, column visibility.
- Drawer/Sheet cho order/customer detail.
- Debounce search 300–500ms.
- Không reload full page on realtime event.

Chi tiết component/hook: xem source `resources/js/components/`, `resources/js/hooks/`.
