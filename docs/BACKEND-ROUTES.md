# ERM SaleOps — Backend routes cần có

Base API: `/api/v1`

Frontend Inertia routes nằm trong `web.php`. REST/mobile/integration routes nằm trong `api.php`.

## 1. Quy ước response

### Success

```json
{
  "success": true,
  "message": "optional",
  "data": {}
}
```

### Error

```json
{
  "success": false,
  "message": "Something went wrong"
}
```

### Validation error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field": ["Error message"]
  }
}
```

### Pagination

Collection response dùng Laravel pagination, gồm:

- `data`
- `meta`
- `links`

Frontend type nên map vào `IPaginationResponse`, `IErrorPaginationResponse`, `IPaginatedResponse` trong `backend.d.ts`.

## 2. Middleware

| Middleware | Dùng cho |
|------------|----------|
| `auth:sanctum` | API cần Bearer token |
| `verified` | Web/Inertia nếu bật email verify |
| `role:admin` | Admin-only routes |
| `role:sales` | Sales-only routes |
| `throttle:api` | API public/protected |
| `webhook.signature` | Webhook platform |

## 3. Auth API

| Method | Path | Middleware | Controller | Mục đích |
|--------|------|------------|------------|----------|
| `POST` | `/auth/token` | `throttle:api` | `AuthTokenController@store` | Login, tạo Sanctum token |
| `GET` | `/auth/me` | `auth:sanctum` | `AuthMeController` | Lấy user hiện tại |
| `DELETE` | `/auth/token` | `auth:sanctum` | `AuthTokenController@destroy` | Thu hồi token hiện tại |

## 4. Dashboard API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/dashboard/summary` | `auth:sanctum` | KPI tổng quan theo role |
| `GET` | `/dashboard/live` | `auth:sanctum` | Snapshot realtime badges/charts |

Scope:

- Admin: toàn hệ thống.
- Sales: chỉ dữ liệu của mình.

## 5. Orders API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/orders` | `auth:sanctum` | Danh sách đơn, search/filter/pagination |
| `POST` | `/orders` | `auth:sanctum` | Tạo đơn thủ công |
| `GET` | `/orders/{order}` | `auth:sanctum` | Chi tiết đơn |
| `PUT` | `/orders/{order}` | `auth:sanctum` | Cập nhật đơn |
| `PATCH` | `/orders/{order}/status` | `auth:sanctum` | Cập nhật trạng thái vận hành |
| `DELETE` | `/orders/{order}` | `auth:sanctum`, `role:admin` | Xóa/hủy mềm đơn |

Query nên hỗ trợ:

- `search`
- `date_from`
- `date_to`
- `date_type`
- `source_type`
- `delivery_status`
- `reconciliation_status`
- `sale_id`
- `marketer_id`
- `product_id`
- `warehouse_id`
- `page`
- `per_page`

## 6. Sale operations API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/sales/workspace` | `auth:sanctum` | Data cho màn sale tác nghiệp |
| `GET` | `/sales/operation-tabs` | `auth:sanctum` | Count theo pipeline tab |
| `POST` | `/orders/{order}/operations` | `auth:sanctum` | Ghi log gọi/chăm sóc |
| `PATCH` | `/orders/{order}/assign-sale` | `auth:sanctum`, `role:admin` | Gán sale phụ trách |
| `PATCH` | `/orders/{order}/closing-status` | `auth:sanctum` | Cập nhật trạng thái chốt |
| `POST` | `/orders/{order}/notes` | `auth:sanctum` | Thêm ghi chú sale |

Pipeline stages:

- `new_customer`
- `call_2`
- `call_3`
- `call_4`
- `call_5`
- `call_6`
- `care_1`
- `care_2`
- `care_3`
- `skipped`
- `no_operation`
- `all`

## 7. Customers API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/customers` | `auth:sanctum` | Danh sách khách hàng |
| `POST` | `/customers` | `auth:sanctum` | Tạo khách hàng |
| `GET` | `/customers/{customer}` | `auth:sanctum` | Hồ sơ khách hàng |
| `PUT` | `/customers/{customer}` | `auth:sanctum` | Cập nhật khách hàng |
| `GET` | `/customers/{customer}/orders` | `auth:sanctum` | Lịch sử đơn |
| `GET` | `/customers/{customer}/timeline` | `auth:sanctum` | Timeline tương tác |

## 8. Leads API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/leads` | `auth:sanctum` | Log lead ingestion |
| `POST` | `/leads` | `auth:sanctum` | Tạo lead từ landing/app có token |
| `GET` | `/leads/{lead}` | `auth:sanctum` | Chi tiết ingestion |
| `POST` | `/leads/{lead}/retry` | `auth:sanctum`, `role:admin` | Retry xử lý lead lỗi |
| `POST` | `/leads/{lead}/convert` | `auth:sanctum` | Convert lead thành order nếu chưa có |

Filter:

- `platform`
- `status`
- `date_from`
- `date_to`
- `phone`
- `utm_source`
- `utm_campaign`

## 9. Webhooks API

Không dùng Bearer token. Xác thực theo platform: verify token, HMAC signature hoặc API key.

| Method | Path | Mục đích |
|--------|------|----------|
| `GET` | `/webhooks/facebook` | Verify Facebook challenge |
| `POST` | `/webhooks/facebook` | Nhận Facebook Lead Ads |
| `POST` | `/webhooks/tiktok` | Nhận TikTok lead |
| `POST` | `/webhooks/zalo` | Nhận Zalo OA lead |
| `POST` | `/webhooks/landing` | Nhận lead từ landing form |
| `POST` | `/webhooks/google` | Nhận Google Lead Form |

Webhook flow:

```text
Request
  → Verify signature/API key
  → Platform driver normalize payload
  → LeadIngestionService
  → lead_ingestions row
  → customer/order upsert
  → broadcast LeadIngested
```

## 10. Integrations API

Admin-only.

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/integrations` | `auth:sanctum`, `role:admin` | Danh sách tích hợp |
| `GET` | `/integrations/{platform}` | `auth:sanctum`, `role:admin` | Chi tiết cấu hình |
| `PUT` | `/integrations/{platform}` | `auth:sanctum`, `role:admin` | Cập nhật cấu hình |
| `POST` | `/integrations/{platform}/test` | `auth:sanctum`, `role:admin` | Test connection/webhook secret |
| `POST` | `/integrations/{platform}/rotate-secret` | `auth:sanctum`, `role:admin` | Đổi webhook secret |

Platforms:

- `facebook`
- `tiktok`
- `zalo`
- `landing`
- `google`
- `ghtk`
- `ghn`
- `viettelpost`
- `voip`

## 11. Reports API

Dùng cho Inertia partial reload hoặc external API.

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/reports/ceo` | `auth:sanctum`, `role:admin` | Báo cáo CEO |
| `GET` | `/reports/marketing-dashboard` | `auth:sanctum`, `role:admin` | Dashboard marketing |
| `GET` | `/reports/marketing-revenue` | `auth:sanctum`, `role:admin` | Báo cáo DS marketing |
| `GET` | `/reports/sales-revenue` | `auth:sanctum` | Báo cáo DS sale |
| `GET` | `/reports/export` | `auth:sanctum`, `role:admin` | Export report CSV/XLSX |

Common filters:

- `source_type`
- `date_from`
- `date_to`
- `date_type`
- `delivery_status`
- `reconciliation_status`
- `discount_mode`
- `parent_product_id`
- `product_id`
- `team_leader_id`
- `team_id`
- `sale_id`
- `marketing_team_leader_id`
- `marketing_team_id`
- `marketer_id`
- `warehouse_id`
- `shipping_method`
- `no_closing_date_limit`

## 12. Accounting API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/accounting/orders` | `auth:sanctum`, `role:admin` | Danh sách đơn cần kế toán xử lý |
| `PATCH` | `/orders/{order}/payment` | `auth:sanctum`, `role:admin` | Cập nhật thanh toán |
| `PATCH` | `/orders/{order}/reconciliation` | `auth:sanctum`, `role:admin` | Cập nhật đối soát |
| `POST` | `/orders/{order}/refunds` | `auth:sanctum`, `role:admin` | Ghi nhận hoàn/refund |
| `GET` | `/accounting/summary` | `auth:sanctum`, `role:admin` | Tổng hợp COD/CK/công nợ |

## 13. Warehouse API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/warehouse/orders` | `auth:sanctum`, `role:admin` | Đơn cần kho xử lý |
| `PATCH` | `/orders/{order}/warehouse-status` | `auth:sanctum`, `role:admin` | Cập nhật trạng thái kho |
| `PATCH` | `/orders/{order}/shipping` | `auth:sanctum`, `role:admin` | Cập nhật vận chuyển |
| `POST` | `/orders/{order}/shipments` | `auth:sanctum`, `role:admin` | Tạo vận đơn |
| `POST` | `/orders/{order}/returns` | `auth:sanctum`, `role:admin` | Ghi nhận hoàn hàng |

## 14. Inventory/Product API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/products` | `auth:sanctum` | Danh sách sản phẩm |
| `POST` | `/products` | `auth:sanctum`, `role:admin` | Tạo sản phẩm |
| `GET` | `/products/{product}` | `auth:sanctum` | Chi tiết sản phẩm |
| `PUT` | `/products/{product}` | `auth:sanctum`, `role:admin` | Cập nhật sản phẩm |
| `DELETE` | `/products/{product}` | `auth:sanctum`, `role:admin` | Xóa mềm sản phẩm |
| `GET` | `/inventory` | `auth:sanctum` | Tồn kho |
| `PATCH` | `/inventory/{inventoryItem}` | `auth:sanctum`, `role:admin` | Cập nhật tồn |
| `POST` | `/inventory/adjustments` | `auth:sanctum`, `role:admin` | Nhập/xuất/điều chỉnh tồn |

## 15. Failed orders API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/orders/failed` | `auth:sanctum`, `role:admin` | Danh sách đơn lỗi |
| `GET` | `/orders/failed/summary` | `auth:sanctum`, `role:admin` | Count lỗi theo loại |
| `POST` | `/orders/{order}/retry-sync` | `auth:sanctum`, `role:admin` | Retry sync vận chuyển/kế toán |
| `PATCH` | `/orders/{order}/resolve-failure` | `auth:sanctum`, `role:admin` | Đánh dấu đã xử lý lỗi |

## 16. Settings API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/settings/profile` | `auth:sanctum` | Profile user |
| `PUT` | `/settings/profile` | `auth:sanctum` | Cập nhật profile |
| `GET` | `/settings/preferences` | `auth:sanctum` | Theme/noti preferences |
| `PUT` | `/settings/preferences` | `auth:sanctum` | Cập nhật preferences |
| `PUT` | `/settings/password` | `auth:sanctum` | Đổi mật khẩu |

## 17. Notifications API

| Method | Path | Middleware | Mục đích |
|--------|------|------------|----------|
| `GET` | `/notifications` | `auth:sanctum` | Danh sách thông báo |
| `PATCH` | `/notifications/{notification}/read` | `auth:sanctum` | Đánh dấu đã đọc |
| `PATCH` | `/notifications/read-all` | `auth:sanctum` | Đọc tất cả |

## 18. Realtime channels

| Channel | Người nghe | Mục đích |
|---------|------------|----------|
| `dashboard.admin` | Admin | Lead/order/report realtime toàn hệ thống |
| `dashboard.sales.{userId}` | Sales cụ thể | Lead/order được gán cho sale |
| `orders.{orderId}` | User có quyền xem order | Chi tiết order realtime |

Events:

- `LeadIngested`
- `OrderCreated`
- `OrderUpdated`
- `OrderStatusChanged`
- `OrderAssigned`
- `PaymentReconciled`
- `InventoryLowStock`

## 19. Inertia web backend routes

Các route này trả page React, không phải JSON API.

| Method | Path | Middleware | Page |
|--------|------|------------|------|
| `GET` | `/admin/dashboard` | `auth`, `role:admin` | `Admin/Dashboard` |
| `GET` | `/admin/reports/ceo` | `auth`, `role:admin` | `Admin/Reports/Ceo` |
| `GET` | `/admin/marketing/dashboard` | `auth`, `role:admin` | `Admin/Marketing/Dashboard` |
| `GET` | `/admin/marketing/revenue` | `auth`, `role:admin` | `Admin/Marketing/Revenue` |
| `GET` | `/admin/sales/revenue` | `auth`, `role:admin` | `Admin/Sales/Revenue` |
| `GET` | `/admin/accounting` | `auth`, `role:admin` | `Admin/Accounting/Index` |
| `GET` | `/admin/warehouse/operations` | `auth`, `role:admin` | `Admin/Warehouse/Operations` |
| `GET` | `/admin/warehouse/inventory` | `auth`, `role:admin` | `Admin/Warehouse/Inventory` |
| `GET` | `/admin/orders/failed` | `auth`, `role:admin` | `Admin/Orders/Failed` |
| `GET` | `/sales/workspace` | `auth`, `role:sales` | `Sales/Workspace` |
| `GET` | `/sales/customers` | `auth`, `role:sales` | `Sales/Customers` |
| `GET` | `/settings` | `auth` | `Settings/Index` |
